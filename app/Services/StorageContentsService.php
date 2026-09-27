<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Store;
use App\Models\Warehouse;
use Illuminate\Support\Collection;

/**
 * Что фактически лежит в хранилище или на складе.
 *
 * Дерево собирается из существующей иерархии store.parent_id, а предметы
 * вешаются на те узлы, в которых лежат. Предметы бывают и в промежуточных
 * узлах («Верхняя полка» внутри «Мастерской»), поэтому у узла три числа:
 * сколько лежит прямо в нём (items_count), сколько из них показано
 * (items), сколько спрятано под «показать все» (items_hidden) и сколько
 * во всём поддереве (items_total).
 *
 * Удалённые хранилища в дереве остаются — с пометкой. Предметы в них живые,
 * а это единственное место, где видно, где они лежат; спрятать их вместе с
 * удалённым хранилищем значило бы вычеркнуть предмет из обзора размещения.
 * Исключение — верхний уровень склада: там удалённое хранилище в дерево не
 * попадает, иначе вкладка начиналась бы с того, чего на складе уже нет.
 *
 * Данные берутся тремя запросами — узлы, предметы, сборка в PHP. По запросу
 * на узел вышло бы по сотне обращений к базе за один показ вкладки.
 */
class StorageContentsService
{
    /** Сколько предметов показывать в узле до кнопки «показать все». */
    public const ITEMS_PREVIEW = 20;

    /**
     * Дерево содержимого хранилища: само хранилище — корень, дальше потомки.
     *
     * @return array<string, mixed>
     */
    public function treeForStore(Store $store): array
    {
        // Верхний уровень — само хранилище, и его пометка [удалено] здесь
        // осмысленна: открыть удалённое хранилище можно, посмотреть, что
        // внутри, и восстановить.
        $nodes = $this->storesStartingFrom([$store->id], skipDeleted: false);
        $all = $nodes->values()->all();
        $assembled = $this->assemble(
            $all,
            $this->itemsByStore($nodes->keys()->all()),
            $this->childrenIndex($nodes),
            array_map(fn ($node) => (int) $node['id'], $all),
        );

        return $assembled[0] ?? $this->emptyNode($store->id, $store->title, $store->trashed());
    }

    /**
     * Дерево содержимого склада: верхний уровень — хранилища склада, дальше
     * их потомки.
     *
     * @return array<string, mixed>
     */
    public function treeForWarehouse(Warehouse $warehouse): array
    {
        $topIds = Store::query()
            ->where('warehouse_id', $warehouse->id)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $nodes = $this->storesStartingFrom($topIds, skipDeleted: true);

        // Хранилище может быть одновременно привязано к складу и вложено в
        // другое хранилище («Верхняя полка» — и в «Мастерскую», и прямо к
        // складу). Показывать его в обоих местах нельзя: получится два
        // узла с одним и тем же содержимым, и сумма по складу удвоится.
        // Верхним уровнем остаются только те, у кого нет родителя среди
        // загруженных узлов, — вложенные показываются внутри своего родителя.
        $nested = [];
        foreach ($nodes as $node) {
            if ($node['parent_id'] !== null && $nodes->has($node['parent_id'])) {
                $nested[] = (int) $node['id'];
            }
        }

        $topNodes = $nodes->reject(fn ($node) => in_array((int) $node['id'], $nested, true));

        $all = $nodes->values()->all();
        $roots = $this->assemble(
            $topNodes->values()->all(),
            $this->itemsByStore($nodes->keys()->all()),
            $this->childrenIndex($nodes),
            array_map(fn ($node) => (int) $node['id'], $all),
        );

        return [
            'id'            => $warehouse->id,
            'title'         => $warehouse->title,
            'kind'          => 'warehouse',
            'deleted'       => $warehouse->trashed(),
            // Предметов у самого склада нет: они лежат в хранилищах.
            'items_count'   => 0,
            'items'         => [],
            'items_hidden'  => 0,
            'items_total'   => $this->sumTotals($roots),
            'children'      => $roots,
        ];
    }

    /**
     * Все предметы хранилища — для кнопки «показать все».
     *
     * Отдельный запрос намеренно: дерево отдаёт по двадцать предметов на
     * узел, а полный список нужен только когда человек его раскрыл.
     *
     * @return array<int, array<string, mixed>>
     */
    public function allItemsOf(Store $store): array
    {
        return $this->itemsByStore([$store->id])[(int) $store->id] ?? [];
    }

    /**
     * Превращает плоский список узлов в дерево, считая итоги снизу вверх.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, array<int, array<string, mixed>>>  $items  предметы всех узлов сразу
     * @param  array<int, array<int, array<string, mixed>>>  $childrenByParent  индекс детей по всему дереву
     * @param  int[]  $knownIds  id всех загруженных узлов, одинаковые на всей рекурсии
     * @return array<int, array<string, mixed>>
     */
    private function assemble(array $nodes, array $items, array $childrenByParent, array $knownIds): array
    {
        if ($nodes === []) {
            return [];
        }

        $result = [];

        foreach ($nodes as $node) {
            $id = (int) $node['id'];

            // Дети берём только из уже загруженных узлов: хранилище может
            // ссылаться на родителя, которого нет в выборке (доступ закрыт
            // или он не входит в поддерево), и такой «сосед» попал бы в
            // дерево дважды — с другой стороны от другой ветви.
            $childNodes = array_values(array_filter(
                $childrenByParent[$id] ?? [],
                fn (array $child) => in_array((int) $child['id'], $knownIds, true)
            ));

            $subtree = $this->assemble($childNodes, $items, $childrenByParent, $knownIds);
            $own = $items[$id] ?? [];

            $result[] = [
                'id'           => $id,
                'title'        => $node['title'],
                'kind'         => 'store',
                'deleted'      => $node['deleted'],
                'items_count'  => count($own),
                'items'        => array_slice($own, 0, self::ITEMS_PREVIEW),
                'items_hidden' => count($own) > self::ITEMS_PREVIEW ? count($own) - self::ITEMS_PREVIEW : 0,
                'items_total'  => count($own) + $this->sumTotals($subtree),
                'children'     => $subtree,
            ];
        }

        return $result;
    }

    /**
     * Индекс «дети по родителю» на всё дерево сразу.
     *
     * Строится один раз и уходит в рекурсию: если собирать его в каждом
     * вызове, сборка видит только узлы своего уровня и не находит внуков —
     * дерево получается глубиной в одну ступень, причём молча.
     *
     * @param  Collection<int, array<string, mixed>>  $nodes
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function childrenIndex(Collection $nodes): array
    {
        $children = [];

        foreach ($nodes as $node) {
            $parentId = $node['parent_id'];

            if ($parentId !== null) {
                $children[(int) $parentId][] = $node;
            }
        }

        return $children;
    }

    /** Сумма items_total по готовым узлам — итого по поддереву. */
    private function sumTotals(array $nodes): int
    {
        $sum = 0;

        foreach ($nodes as $node) {
            $sum += (int) ($node['items_total'] ?? 0);
        }

        return $sum;
    }

    /**
     * Хранилища начиная с указанных: сами эти и все потомки.
     *
     * Обход по уровням, а не рекурсивный CTE: дерево неглубокое, а список
     * уровней всё равно известен на каждом шаге, так что обход читается
     * прямо и упирается в пару запросов на страницу.
     *
     * @param  int[]  $rootIds
     * @return Collection<int, array<string, mixed>>
     */
    private function storesStartingFrom(array $rootIds, bool $skipDeleted): Collection
    {
        if ($rootIds === []) {
            return new Collection();
        }

        // withTrashed(): удалённое хранилище показываем (см. docblock).
        $nodes = new Collection();

        foreach ($this->fetchStoresById($rootIds) as $store) {
            if ($skipDeleted && $store->trashed()) {
                continue;
            }

            $nodes->put($store->id, $this->nodeOf($store));
        }

        $pending = $nodes->keys()->all();

        while ($pending !== []) {
            // Запрос делается по текущему уровню, и только потом pending
            // очищается: если обнулить заранее, обход спрашивает по пустому
            // списку и молча останавливается на первом же уровне.
            $children = $this->fetchStoresByParent($pending);
            $pending = [];

            foreach ($children as $child) {
                if ($nodes->has($child->id)) {
                    continue;
                }

                $nodes->put($child->id, $this->nodeOf($child));
                $pending[] = $child->id;
            }
        }

        return $nodes;
    }

    /**
     * Хранилища по списку id — это стартовые узлы обхода.
     *
     * Отдельный метод не для красоты, а потому что обход идёт по двум
     * разным признакам: старт берём по id (это либо само хранилище, либо
     * верхний уровень склада), а следующий шаг — по parent_id. Один
     * запрос на оба признака молча зацикливал бы обход на первом же уровне.
     *
     * @return Collection<int, Store>
     */
    private function fetchStoresById(array $ids): Collection
    {
        return Store::withTrashed()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get(['id', 'title', 'parent_id', 'warehouse_id', 'deleted_at']);
    }

    /**
     * Потомки указанных хранилищ — следующий шаг обхода.
     *
     * @return Collection<int, Store>
     */
    private function fetchStoresByParent(array $parentIds): Collection
    {
        return Store::withTrashed()
            ->whereIn('parent_id', $parentIds)
            ->orderBy('id')
            ->get(['id', 'title', 'parent_id', 'deleted_at']);
    }

    /** @return array<string, mixed> */
    private function nodeOf(Store $store): array
    {
        return [
            'id'        => $store->id,
            'title'     => $store->title,
            'parent_id' => $store->parent_id,
            'deleted'   => $store->trashed(),
        ];
    }

    /**
     * Живые предметы указанных хранилищ, сгруппированные по хранилищу.
     *
     * Скоупы модели отвечают и за права: предмет, к которому у человека нет
     * доступа, в дереве не появится.
     *
     * @param  int[]  $storeIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function itemsByStore(array $storeIds): array
    {
        if ($storeIds === []) {
            return [];
        }

        $grouped = [];

        Item::query()
            ->whereIn('store_id', $storeIds)
            ->orderBy('title')
            ->get()
            ->each(function (Item $item) use (&$grouped): void {
                $grouped[(int) $item->store_id][] = [
                    'id'    => $item->id,
                    'title' => $item->title,
                ];
            });

        return $grouped;
    }

    /** @return array<string, mixed> */
    private function emptyNode(int $id, string $title, bool $deleted): array
    {
        return [
            'id'           => $id,
            'title'        => $title,
            'kind'         => 'store',
            'deleted'      => $deleted,
            'items_count'  => 0,
            'items'        => [],
            'items_hidden' => 0,
            'items_total'  => 0,
            'children'     => [],
        ];
    }
}

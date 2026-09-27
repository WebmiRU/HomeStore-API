<?php

namespace App\Http\Controllers;

use App\Http\Requests\TrashActionRequest;
use App\Http\Resources\TrashEntryResource;
use App\Services\TrashService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Корзина: удалённые записи, восстановление и окончательное удаление.
 *
 * Маршруты намеренно с типом в адресе (/trash/item, /trash/category), а не
 * с типом в теле: список корзины переключается вкладками, и вкладка должна
 * быть ссылкой, которую можно открыть, отправить другому и вернуться по
 * кнопке «назад».
 *
 * Ответы списка и действий разные по своей природе: список — это пагинация
 * ресурсов, а восстановление/удаление — это сводка («восстановлено 12,
 * отказано 2») с перечнем отказов, потому что по галочкам результат не
 * угадать.
 */
class TrashController extends Controller
{
    public function __construct(private readonly TrashService $trash)
    {
    }

    /**
     * GET /api/trash/{section}
     */
    public function index(string $section, Request $request): ResourceCollection
    {
        $this->trash->modelFor($section);

        // Своих правил у списка нет: кроме номера страницы там нечего
        // проверять, а TrashActionRequest требует ids и для него не годится.
        $paginator = $this->trash->listing($section, $request->integer('page', 1));

        return TrashEntryResource::collection($paginator)->additional([
            'can_purge' => $this->trash->purgeAllowed($section),
        ]);
    }

    /**
     * GET /api/trash/counts — счётчики удалённых по всем разделам.
     *
     * Отдельный маршрут, а не поле в каждом списке: вкладкам нужны все
     * тринадцать чисел сразу, иначе пришлось бы тринадцать раз открывать
     * каждый раздел, чтобы узнать, где что лежит.
     *
     * Путь в обход списков: значение 'counts' не совпадает ни с одним
     * разделом, поэтому попасть сюда случайно нельзя.
     */
    public function counts(): JsonResponse
    {
        return response()->json(['counts' => $this->trash->counts()]);
    }

    /**
     * POST /api/trash/{section}/restore
     */
    public function restore(string $section, TrashActionRequest $request): JsonResponse
    {
        $this->trash->modelFor($section);

        $result = $this->trash->restore($section, $request->validated('ids'));

        return response()->json([
            'restored' => $result['restored'],
            'failed'   => $result['failed'],
        ]);
    }

    /**
     * POST /api/trash/{section}/purge — окончательное удаление.
     */
    public function purge(string $section, TrashActionRequest $request): JsonResponse
    {
        $this->trash->modelFor($section);

        $result = $this->trash->purge($section, $request->validated('ids'));

        return response()->json([
            'purged' => $result['purged'],
            'titles' => $result['titles'],
        ]);
    }
}

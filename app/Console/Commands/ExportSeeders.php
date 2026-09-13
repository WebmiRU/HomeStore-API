<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ExportSeeders extends Command
{
    protected $signature = 'seeders:export {--table= : Export a specific table}';
    protected $description = 'Export current database data into seeders';

    public function handle(): int
    {
        $tables = $this->option('table');
        $tables = $tables ? array_map('trim', explode(',', $tables)) : [
            'code',
            'label_list',
        ];

        $exporters = [
            'code' => fn () => $this->exportTable('code', 'Code', ['id', 'code', 'item_id', 'store_id']),
            'label_list' => fn () => $this->exportLabelList(),
        ];

        foreach ($tables as $table) {
            if (isset($exporters[$table])) {
                $this->info("Exporting {$table}...");
                $exporters[$table]();
            } else {
                $this->warn("No exporter for table: {$table}");
            }
        }

        $this->info('Done!');

        return 0;
    }

    protected function exportTable(string $table, string $seederName, array $columns): void
    {
        $rows = DB::table($table)->get();

        if ($rows->isEmpty()) {
            $this->warn("Table '{$table}' is empty, skipping.");
            return;
        }

        // Build data array
        $data = [];
        foreach ($rows as $row) {
            $record = [];
            foreach ($columns as $col) {
                $val = $row->$col;
                if (is_null($val)) {
                    $record[$col] = 'null';
                } elseif ($val === 0) {
                    $record[$col] = '0';
                } elseif (is_bool($val)) {
                    $record[$col] = $val ? 'true' : 'false';
                } else {
                    $record[$col] = addslashes((string) $val);
                }
            }
            $data[] = $record;
        }

        $filePath = database_path("seeders/{$seederName}Seeder.php");

        $php = "<?php\n\n";
        $php .= "namespace Database\\Seeders;\n\n";
        $php .= "use Illuminate\\Database\\Seeder;\n";
        $php .= "use Illuminate\\Support\\Facades\\DB;\n\n";
        $php .= "class {$seederName}Seeder extends Seeder\n";
        $php .= "{\n";
        $php .= "    public function run(): void\n";
        $php .= "    {\n";
        $php .= "        \$data = " . $this->varToPhp($data) . ";\n\n";

        $php .= "        foreach (\$data as \$record) {\n";
        $php .= "            DB::table('{$table}')->updateOrInsert(\n";
        $php .= "                ['id' => \$record['id']],\n";
        $php .= "                \$record + ['updated_at' => now()]\n";
        $php .= "            );\n";
        $php .= "        }\n";

        $php .= "    }\n";
        $php .= "}\n";

        File::put($filePath, $php);
        $this->info("  -> {$filePath}");
    }

    protected function exportLabelList(): void
    {
        $lists = DB::table('label_list')->get();

        if ($lists->isEmpty()) {
            $this->warn("label_list is empty, skipping.");
            return;
        }

        $filePath = database_path('seeders/LabelListSeeder.php');

        $php = "<?php\n\n";
        $php .= "namespace Database\\Seeders;\n\n";
        $php .= "use App\\Models\\LabelList;\n";
        $php .= "use Illuminate\\Database\\Seeder;\n\n";
        $php .= "class LabelListSeeder extends Seeder\n";
        $php .= "{\n";
        $php .= "    public function run(): void\n";
        $php .= "    {\n";

        foreach ($lists as $list) {
            $items = DB::table('label_list_m2m_item')
                ->where('label_list_id', $list->id)
                ->orderBy('item_id')
                ->pluck('item_id')
                ->toArray();

            $stores = DB::table('label_list_m2m_store')
                ->where('label_list_id', $list->id)
                ->orderBy('store_id')
                ->pluck('store_id')
                ->toArray();

            $php .= "\n";
            $php .= "        // List: {$list->title}\n";
            $php .= "        \$list" . $list->id . " = LabelList::create([\n";
            $php .= "            'title' => '" . addslashes($list->title) . "',\n";
            $php .= "            'label_preset_id' => {$list->label_preset_id},\n";
            $php .= "        ]);\n";

            if (!empty($items)) {
                $php .= "        \$list" . $list->id . "->items()->sync(" . json_encode($items) . ");\n";
            }

            if (!empty($stores)) {
                $php .= "        \$list" . $list->id . "->stores()->sync(" . json_encode($stores) . ");\n";
            }
        }

        $php .= "\n";
        $php .= "    }\n";
        $php .= "}\n";

        File::put($filePath, $php);
        $this->info("  -> {$filePath}");
    }

    protected function varToPhp(array $data): string
    {
        $lines = ["["];

        foreach ($data as $idx => $record) {
            $lines[] = "            [";
            foreach ($record as $key => $val) {
                if ($val === 'null') {
                    $lines[] = "                '{$key}' => null,";
                } elseif ($val === 'true') {
                    $lines[] = "                '{$key}' => true,";
                } elseif ($val === 'false') {
                    $lines[] = "                '{$key}' => false,";
                } elseif (is_numeric($val)) {
                    $lines[] = "                '{$key}' => {$val},";
                } else {
                    $lines[] = "                '{$key}' => '" . addslashes($val) . "',";
                }
            }
            $lines[] = "            ],";
        }

        $lines[] = "        ]";
        return implode("\n", $lines);
    }
}

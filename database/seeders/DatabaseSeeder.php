<?php

namespace Database\Seeders;

use App\Models\Code;
use App\Models\Item;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $labels = json_decode(file_get_contents(__DIR__ . '/../../test_100_labels.json'), true);
        $titles = array_column($labels['labels'], 'title');

        // Box distribution per shelf: [shelf1_count, shelf2_count]
        $boxDistribution = [
            [1, 2], // Cabinet 1
            [1, 2], // Cabinet 2
            [1, 2], // Cabinet 3
        ];

        $boxes = [];
        $boxCounter = 0;
        $codeSeq = 0;

        // Create stores hierarchy (cabinets → shelves → boxes)
        for ($i = 1; $i <= 3; $i++) {
            $cabinet = Store::create(['title' => "Шкаф {$i}"]);

            Code::create([
                'code'     => $this->staticUuidV7(++$codeSeq, 'cabinet'),
                'store_id' => $cabinet->id,
            ]);

            [$shelf1Count, $shelf2Count] = $boxDistribution[$i - 1];

            $shelves = [
                Store::create([
                    'title'     => "Полка {$i}-1",
                    'parent_id' => $cabinet->id,
                ]),
                Store::create([
                    'title'     => "Полка {$i}-2",
                    'parent_id' => $cabinet->id,
                ]),
            ];

            foreach ($shelves as $shelf) {
                Code::create([
                    'code'     => $this->staticUuidV7(++$codeSeq, 'shelf'),
                    'store_id' => $shelf->id,
                ]);
            }

            $shelfBoxCounts = [$shelf1Count, $shelf2Count];

            foreach ($shelfBoxCounts as $shelfIndex => $boxCount) {
                for ($b = 0; $b < $boxCount; $b++) {
                    $boxCounter++;
                    $box = Store::create([
                        'title'     => "Коробка {$boxCounter}",
                        'parent_id' => $shelves[$shelfIndex]->id,
                    ]);

                    Code::create([
                        'code'     => $this->staticUuidV7(++$codeSeq, 'box'),
                        'store_id' => $box->id,
                    ]);

                    $boxes[] = $box;
                }
            }
        }

        // Distribute 100 items across boxes
        $totalBoxes = count($boxes);
        foreach ($titles as $index => $title) {
            $box = $boxes[$index % $totalBoxes];

            $item = Item::create([
                'title'    => $title,
                'store_id' => $box->id,
            ]);

            Code::create([
                'code'    => $this->staticUuidV7(++$codeSeq, 'item'),
                'item_id' => $item->id,
            ]);
        }
    }

    /**
     * Generate a deterministic UUID v7.
     * Uses a fixed timestamp base plus a sequence number for uniqueness,
     * and SHA-256 of (prefix.seq) for the random part — so the same
     * prefix+seq always produces the same UUID.
     */
    private function staticUuidV7(int $seq, string $prefix): string
    {
        // Fixed base timestamp: 2026-01-01 00:00:00.000 UTC
        $tsMs = 1767225600000 + $seq;
        $tsHex = str_pad(dechex($tsMs), 12, '0', STR_PAD_LEFT);

        // Deterministic «random» bytes from SHA-256
        $hash = hash('sha256', "{$prefix}.{$seq}");

        $timeHigh = substr($tsHex, 0, 8);
        $timeMid  = substr($tsHex, 8, 4);

        // version 7 (4 bits) + rand_a (12 bits)
        $verRandA = '7' . substr($hash, 0, 3);

        // variant 10xx (2 bits) + first 14 bits of rand_b
        $varRandB1 = dechex(0x8000 | hexdec(substr($hash, 3, 4)));

        // remaining 48 bits of rand_b
        $randB2 = substr($hash, 7, 12);

        return sprintf('%s-%s-%s-%s-%s', $timeHigh, $timeMid, $verRandA, $varRandB1, $randB2);
    }
}

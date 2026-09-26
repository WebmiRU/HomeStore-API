<?php

namespace App\Http\Resources;

use App\Enums\StockDirection;
use App\Models\StockOperation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockOperationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $direction = $this->direction instanceof StockDirection
            ? $this->direction
            : StockDirection::from((string) $this->direction);

        $rows = $this->whenLoaded('rows', fn () => $this->rows);

        return [
            'id'            => $this->id,
            'direction'     => $direction->value,
            'direction_label' => $direction->label(),
            'comment'       => $this->comment,
            'user_id'       => $this->user_id,
            'author'        => $this->whenLoaded('author', fn () => $this->author ? [
                'id'   => $this->author->id,
                'name' => $this->author->name,
            ] : null),
            'reversed_operation_id' => $this->reversed_operation_id,
            'reversed_at'   => $this->reversed_at?->toIso8601String(),
            'is_reversal'   => $this->isReversal(),
            'is_reversed'   => $this->isReversed(),
            'created_at'    => $this->created_at?->toIso8601String(),
            'rows'          => $rows === null ? [] : $rows->map(fn ($row) => [
                'id'               => $row->id,
                'item_id'          => $row->item_id,
                'item_title'       => $row->item_title,
                'source_row_id'    => $row->source_row_id,
                'quantity'         => $row->quantity,
                'reversed_quantity' => $row->reversed_quantity,
                'remaining'        => $row->remaining(),
                'before'           => $row->before,
                'after'            => $row->after,
                'is_returned'      => $row->reversed_quantity > 0,
            ])->values(),
        ];
    }
}

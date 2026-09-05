<?php

namespace App\Models;

use App\Enums\RuasTransport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu ruas nota transportasi lokal.
 */
class NotaTransport extends Model
{
    protected $table = 'nota_transport';

    protected $fillable = [
        'id_usulan',
        'urutan',
        'nominal',
        'keterangan',
        'bukti',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
            'nominal' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Usulan, $this>
     */
    public function usulan(): BelongsTo
    {
        return $this->belongsTo(Usulan::class, 'id_usulan');
    }

    public function getRuasAttribute(): ?RuasTransport
    {
        return RuasTransport::dari($this->urutan);
    }

    public function getLabelAttribute(): string
    {
        return $this->ruas?->label() ?? 'Ruas '.$this->urutan;
    }

    public function terisi(): bool
    {
        return $this->nominal > 0;
    }
}

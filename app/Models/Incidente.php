<?php

namespace App\Models;

use App\Enums\TipoChequeo;
use Database\Factories\IncidenteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Una falla que sigue abierta, o el registro de una que ya se resolvió.
 *
 * Como máximo uno abierto por proyecto y tipo. Eso lo garantiza
 * `EjecutorDeChequeos` en una transacción, no la base: la restricción natural
 * sería `unique(proyecto_id, tipo, cerrado_at)` y MySQL admite múltiples NULL en
 * un índice único.
 *
 * @property int $id
 * @property int $proyecto_id
 * @property TipoChequeo $tipo
 * @property Carbon $abierto_at
 * @property Carbon|null $cerrado_at
 * @property string|null $ultimo_mensaje
 * @property Carbon|null $avisado_at
 * @property Carbon|null $avisado_cierre_at
 * @property-read Proyecto $proyecto
 */
#[Fillable(['tipo', 'abierto_at', 'cerrado_at', 'ultimo_mensaje', 'avisado_at', 'avisado_cierre_at'])]
class Incidente extends Model
{
    /** @use HasFactory<IncidenteFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'tipo' => TipoChequeo::class,
            'abierto_at' => 'datetime',
            'cerrado_at' => 'datetime',
            'avisado_at' => 'datetime',
            'avisado_cierre_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Proyecto, $this>
     */
    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }

    /**
     * @param  Builder<Incidente>  $query
     */
    public function scopeAbiertos(Builder $query): void
    {
        $query->whereNull('cerrado_at');
    }

    public function estaAbierto(): bool
    {
        return $this->cerrado_at === null;
    }

    /**
     * Cuánto duró, o cuánto lleva si sigue abierto.
     */
    public function duracion(): string
    {
        return $this->abierto_at->diffForHumans($this->cerrado_at ?? now(), short: true, parts: 2);
    }
}

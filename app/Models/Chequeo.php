<?php

namespace App\Models;

use App\Enums\EstadoChequeo;
use App\Enums\TipoChequeo;
use Database\Factories\ChequeoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * El resultado guardado de una sonda. Una fila por corrida.
 *
 * Es el **resultado**, no el chequeo: la clase que sabe cómo mirar un sitio es
 * una `Sonda`. Los dos nombres se parecen y confundirlos es fácil.
 *
 * @property int $id
 * @property int $proyecto_id
 * @property TipoChequeo $tipo
 * @property EstadoChequeo $estado
 * @property int|null $codigo_http
 * @property int|null $latencia_ms
 * @property array<string, mixed>|null $detalle
 * @property string|null $mensaje
 * @property Carbon $ejecutado_at
 * @property-read Proyecto $proyecto
 */
#[Fillable([
    'tipo',
    'estado',
    'codigo_http',
    'latencia_ms',
    'detalle',
    'mensaje',
    'ejecutado_at',
])]
class Chequeo extends Model
{
    /** @use HasFactory<ChequeoFactory> */
    use HasFactory;

    /**
     * `ejecutado_at` alcanza: un chequeo no se edita nunca, así que un
     * `updated_at` sería siempre igual al otro.
     */
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'tipo' => TipoChequeo::class,
            'estado' => EstadoChequeo::class,
            'detalle' => 'array',
            'ejecutado_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Proyecto, $this>
     */
    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }
}

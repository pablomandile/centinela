<?php

namespace App\Models;

use App\Enums\EstadoChequeo;
use App\Enums\TipoChequeo;
use App\Policies\ProyectoPolicy;
use Carbon\CarbonInterface;
use Database\Factories\ProyectoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Un sitio publicado que Centinela vigila.
 *
 * @property int $id
 * @property string $nombre
 * @property string $slug
 * @property string $url
 * @property string|null $repo_url
 * @property bool $usa_inertia
 * @property bool $es_pwa
 * @property bool $tiene_bundle
 * @property bool $activo
 * @property string|null $palabra_clave
 * @property int $intervalo_minutos
 * @property string|null $notas
 * @property int $orden
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Chequeo> $chequeos
 * @property-read Collection<int, Incidente> $incidentes
 * @property-read Collection<int, Documento> $documentos
 */
#[Fillable([
    'nombre',
    'slug',
    'url',
    'repo_url',
    'usa_inertia',
    'es_pwa',
    'tiene_bundle',
    'activo',
    'palabra_clave',
    'intervalo_minutos',
    'notas',
    'orden',
])]
#[UsePolicy(ProyectoPolicy::class)]
class Proyecto extends Model
{
    /** @use HasFactory<ProyectoFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'usa_inertia' => 'boolean',
            'es_pwa' => 'boolean',
            'tiene_bundle' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // El slug es la URL del detalle y la forma de nombrar un proyecto en la
        // consola (`centinela:chequear --proyecto=huella`). Se deriva del nombre
        // para no pedirlo en el formulario, pero se puede pisar.
        static::creating(function (Proyecto $proyecto): void {
            if (blank($proyecto->slug)) {
                $proyecto->slug = Str::slug($proyecto->nombre);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return HasMany<Chequeo, $this>
     */
    public function chequeos(): HasMany
    {
        return $this->hasMany(Chequeo::class);
    }

    /**
     * @return HasMany<Incidente, $this>
     */
    public function incidentes(): HasMany
    {
        return $this->hasMany(Incidente::class);
    }

    /**
     * @return HasMany<Documento, $this>
     */
    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class);
    }

    /**
     * @param  Builder<Proyecto>  $query
     */
    public function scopeActivos(Builder $query): void
    {
        $query->where('activo', true);
    }

    /**
     * @param  Builder<Proyecto>  $query
     */
    public function scopeOrdenados(Builder $query): void
    {
        $query->orderBy('orden')->orderBy('nombre');
    }

    /**
     * El host de la URL canónica, que es lo que necesitan el certificado y el
     * chequeo de redirección a https.
     */
    public function host(): string
    {
        return (string) parse_url($this->url, PHP_URL_HOST);
    }

    /**
     * Con qué está hecho, en una línea, para mostrar al lado del nombre.
     *
     * Se deriva de las banderas en vez de guardarse: un rótulo guardado se
     * desincroniza del día que la detección cambia una bandera.
     */
    public function etiquetaTecnica(): string
    {
        $partes = [];

        if ($this->usa_inertia) {
            $partes[] = 'Laravel + Inertia';
        } elseif ($this->tiene_bundle) {
            $partes[] = 'SPA';
        } else {
            $partes[] = 'Sin build';
        }

        if ($this->es_pwa) {
            $partes[] = 'PWA';
        }

        return implode(' · ', $partes);
    }

    public function esHttps(): bool
    {
        return str_starts_with($this->url, 'https://');
    }

    /**
     * Una URL del sitio, cuidando la barra del medio.
     */
    public function urlDe(string $ruta): string
    {
        return rtrim($this->url, '/').'/'.ltrim($ruta, '/');
    }

    /**
     * El último chequeo de cada tipo, indexado por el valor del tipo.
     *
     * Una sola consulta: el tablero muestra doce proyectos por seis sondas, y
     * preguntar de a uno serían setenta y dos consultas por pantalla.
     *
     * @return Collection<string, Chequeo>
     */
    public function ultimosChequeos(): Collection
    {
        return $this->chequeos()
            ->whereIn('id', function ($query) {
                $query->selectRaw('MAX(id)')
                    ->from('chequeos')
                    ->where('proyecto_id', $this->id)
                    ->groupBy('tipo');
            })
            ->get()
            ->keyBy(fn (Chequeo $chequeo) => $chequeo->tipo->value);
    }

    /**
     * El peor estado entre los últimos chequeos: el semáforo del tablero.
     *
     * Sin chequeos todavía devuelve null, que **no** es lo mismo que "ok": un
     * proyecto recién cargado no está sano, está sin mirar.
     */
    public function estado(): ?EstadoChequeo
    {
        $estados = $this->ultimosChequeos()->map(fn (Chequeo $chequeo) => $chequeo->estado);

        if ($estados->isEmpty()) {
            return null;
        }

        return $estados->sortByDesc(fn (EstadoChequeo $estado) => $estado->gravedad())->first();
    }

    /**
     * ¿Le toca correr esta sonda?
     *
     * La disponibilidad respeta `intervalo_minutos`; el resto va una vez por día,
     * porque solo cambia cuando hubo un deploy y pegarle cada cuarto de hora a
     * doce sitios para mirar lo mismo gasta el rate limit sin ganar nada.
     *
     * **Las auditorías van corridas entre proyectos.** Sin eso, después de una
     * corrida completa todas quedan alineadas y al día siguiente vencen en el mismo
     * tick: ~90 pedidos en un solo proceso. Medido en el hosting compartido, esa
     * ráfaga estrangula el proceso —las latencias se van de 30 ms a 5-9 segundos y
     * algún pedido llega a morir con "Connection reset by peer"—, o sea que
     * Centinela se inventaría lentitud y hasta una caída que no existe. Corriéndolas
     * un rato por proyecto, cada tick queda liviano.
     */
    public function toca(TipoChequeo $tipo, ?CarbonInterface $ahora = null): bool
    {
        $ahora ??= now();

        $ultimo = $this->chequeos()
            ->where('tipo', $tipo)
            ->orderByDesc('ejecutado_at')
            ->first();

        if ($ultimo === null) {
            return true;
        }

        if ($tipo->esFrecuente()) {
            return $ultimo->ejecutado_at
                ->addMinutes($this->intervalo_minutos)
                ->lessThanOrEqualTo($ahora);
        }

        // Un día, más un desfasaje propio de cada proyecto (hasta 6 h) derivado de su
        // id: es determinístico, así que no se mueve entre corridas.
        $desfasaje = ($this->id % 12) * 30;

        return $ultimo->ejecutado_at
            ->addMinutes(60 * 24 + $desfasaje)
            ->lessThanOrEqualTo($ahora);
    }
}

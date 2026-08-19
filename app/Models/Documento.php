<?php

namespace App\Models;

use App\Enums\FormatoDocumento;
use App\Policies\DocumentoPolicy;
use Database\Factories\DocumentoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Un documento de un proyecto: un `.md` o un `.pdf`.
 *
 * @property int $id
 * @property int $proyecto_id
 * @property string $titulo
 * @property string $slug
 * @property FormatoDocumento $formato
 * @property string $ruta
 * @property string $nombre_original
 * @property int $tamano
 * @property string $hash
 * @property string|null $texto
 * @property string|null $texto_normalizado
 * @property int $orden
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Proyecto $proyecto
 */
#[Fillable(['titulo', 'slug', 'formato', 'ruta', 'nombre_original', 'tamano', 'hash', 'texto', 'texto_normalizado', 'orden'])]
#[UsePolicy(DocumentoPolicy::class)]
class Documento extends Model
{
    /** @use HasFactory<DocumentoFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'formato' => FormatoDocumento::class,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<Proyecto, $this>
     */
    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }

    /**
     * @param  Builder<Documento>  $query
     */
    public function scopeOrdenados(Builder $query): void
    {
        $query->orderBy('orden')->orderBy('titulo');
    }

    /**
     * Busca en el título y en el texto de los markdown.
     *
     * `LIKE` y no `FULLTEXT` a propósito: los tests corren en sqlite, que no lo
     * soporta, y el volumen real son unos pocos MB.
     *
     * Compara contra `texto_normalizado`, que ya trae el título y el contenido en
     * minúsculas y sin acentos: así "documentacion" encuentra "documentación" —que
     * es como uno escribe cuando busca rápido— y funciona igual en MySQL y en
     * sqlite, en vez de depender de la collation.
     *
     * @param  Builder<Documento>  $query
     */
    public function scopeBuscar(Builder $query, string $termino): void
    {
        $termino = trim($termino);

        if ($termino === '') {
            return;
        }

        $query->where('texto_normalizado', 'like', '%'.static::normalizar($termino).'%');
    }

    /**
     * El título y el texto, listos para buscar.
     *
     * El título va adentro para que un PDF —que no tiene texto— igual se pueda
     * encontrar por su nombre, sin necesitar una segunda condición en la consulta.
     */
    public static function textoParaBuscar(string $titulo, ?string $texto): string
    {
        return static::normalizar(trim($titulo."\n".($texto ?? '')));
    }

    /**
     * Minúsculas y sin acentos.
     */
    public static function normalizar(string $texto): string
    {
        return strtr(mb_strtolower($texto), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u', 'ç' => 'c',
        ]);
    }

    /**
     * Un fragmento del texto alrededor del término buscado.
     *
     * Un resultado de búsqueda sin contexto obliga a abrir cada documento para ver
     * si era el que se buscaba.
     */
    public function fragmento(string $termino, int $largo = 180): ?string
    {
        if (blank($this->texto) || blank($termino)) {
            return null;
        }

        $posicion = mb_stripos($this->texto, $termino);

        if ($posicion === false) {
            $posicion = mb_stripos(static::normalizar($this->texto), static::normalizar($termino));
        }

        if ($posicion === false) {
            return null;
        }

        $desde = max(0, $posicion - 60);
        $fragmento = mb_substr($this->texto, $desde, $largo);

        return ($desde > 0 ? '…' : '').trim($fragmento).'…';
    }

    public function tamanoLegible(): string
    {
        return $this->tamano < 1024 * 1024
            ? round($this->tamano / 1024).' KB'
            : round($this->tamano / 1024 / 1024, 1).' MB';
    }
}

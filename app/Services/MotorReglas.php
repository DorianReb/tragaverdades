<?php

namespace App\Services;

/**
 * Motor simbólico para la Unidad III de Inteligencia Artificial.
 * NO ejecuta SaBERT, NO verifica hechos y NO estima probabilidades.
 */
class MotorReglas
{
    private const META = 'recomendar_verificacion';

    /** Base de conocimientos: reglas SI (antecedentes) -> ENTONCES (consecuente). */
    private const REGLAS = [
        ['id'=>'R01','si'=>['exclamaciones_repetidas'],'entonces'=>'estilo_alarmista','prioridad'=>55,'explicacion'=>'Exclamaciones repetidas: conviene revisar el tono del mensaje.'],
        ['id'=>'R02','si'=>['mayusculas_excesivas'],'entonces'=>'estilo_alarmista','prioridad'=>54,'explicacion'=>'Varias palabras en mayúsculas pueden acentuar un estilo alarmista.'],
        ['id'=>'R03','si'=>['titulo_sensacionalista'],'entonces'=>'estilo_alarmista','prioridad'=>60,'explicacion'=>'El lenguaje sensacionalista justifica una revisión del titular.'],
        ['id'=>'R04','si'=>['solicita_difusion','titulo_sensacionalista'],'entonces'=>'presion_viral','prioridad'=>85,'explicacion'=>'Se combina presión para compartir con expresiones sensacionalistas.'],
        ['id'=>'R05','si'=>['fuente_anonima'],'entonces'=>'atribucion_debil','prioridad'=>75,'explicacion'=>'Las fuentes anónimas requieren comprobación independiente.'],
        ['id'=>'R06','si'=>['no_identifica_fuente'],'entonces'=>'atribucion_incompleta','prioridad'=>50,'explicacion'=>'El detector no reconoció referencias identificables en el texto.'],
        ['id'=>'R07','si'=>['afirmacion_extraordinaria'],'entonces'=>'requiere_evidencia','prioridad'=>65,'explicacion'=>'Una afirmación extraordinaria requiere evidencias contrastables.'],
        ['id'=>'R08','si'=>['presion_viral','atribucion_debil'],'entonces'=>'revision_prioritaria','prioridad'=>100,'explicacion'=>'Presión viral y atribución débil justifican una revisión prioritaria.'],
        ['id'=>'R09','si'=>['estilo_alarmista','atribucion_incompleta'],'entonces'=>'revision_prioritaria','prioridad'=>90,'explicacion'=>'Estilo alarmista sin referencias detectadas: revisar antes de compartir.'],
        ['id'=>'R10','si'=>['requiere_evidencia'],'entonces'=>'revision_prioritaria','prioridad'=>95,'explicacion'=>'Se recomienda evaluar la evidencia de una afirmación extraordinaria.'],
        ['id'=>'R11','si'=>['estilo_alarmista'],'entonces'=>'revisar_titular','prioridad'=>45,'explicacion'=>'Conviene revisar el contenido completo y no solo el titular.'],
        ['id'=>'R12','si'=>['atribucion_debil'],'entonces'=>'contrastar_fuentes','prioridad'=>80,'explicacion'=>'Conviene contrastar con fuentes identificables.'],
        ['id'=>'R13','si'=>['revision_prioritaria'],'entonces'=>'advertir_usuario','prioridad'=>75,'explicacion'=>'Se genera una advertencia para promover la revisión crítica.'],
        ['id'=>'R14','si'=>['advertir_usuario'],'entonces'=>'recomendar_verificacion','prioridad'=>70,'explicacion'=>'Se recomienda contrastar la noticia antes de compartirla.'],
        ['id'=>'R15','si'=>['revisar_titular','contrastar_fuentes'],'entonces'=>'recomendar_verificacion','prioridad'=>65,'explicacion'=>'La combinación de indicios lleva a recomendar una verificación.'],
        ['id'=>'R16','si'=>['menciona_fecha'],'entonces'=>'comprobar_fecha','prioridad'=>35,'explicacion'=>'Se recomienda comprobar que la fecha siga siendo pertinente.'],
        ['id'=>'R17','si'=>['cita_fuente'],'entonces'=>'comprobar_fuente','prioridad'=>40,'explicacion'=>'Una cita detectada debe revisarse en la fuente original.'],
    ];

    /**
     * Devuelve datos JSON seguros para mostrar en Blade.
     * El historial se mantiene solo en el navegador, no en la base de datos.
     */
    public function analizar(string $texto): array
    {
        $hechos = $this->extraerHechos($texto);
        $agendaInicial = $this->agenda($hechos);
        [$hechosFinales, $disparadas] = $this->encadenarAdelante($hechos);
        $derivados = array_values(array_diff($hechosFinales, $hechos));
        sort($derivados);

        $metaAlcanzada = in_array(self::META, $hechosFinales, true);
        return [
            'modo' => 'reglas_unidad_iii',
            'meta' => self::META,
            'meta_alcanzada' => $metaAlcanzada,
            'resultado' => $metaAlcanzada ? 'Revisión recomendada' : 'Sin señales suficientes',
            'interpretacion' => $metaAlcanzada
                ? 'Se detectaron señales que justifican contrastar el contenido. Esto NO prueba que sea falso.'
                : 'No se alcanzó la recomendación con estas reglas. Esto NO prueba que sea verdadero.',
            'hechos_iniciales' => $hechos,
            'agenda_inicial' => $agendaInicial,
            'hechos_derivados' => $derivados,
            'reglas_activadas' => $disparadas,
            'total_reglas' => count(self::REGLAS),
            'prueba_hacia_atras' => $this->encadenarAtras(self::META, $hechos, []),
            'bfs' => $this->buscarMeta($hechos, 'anchura'),
            'dfs' => $this->buscarMeta($hechos, 'profundidad'),
            'advertencia' => 'Módulo académico basado en reglas: no ejecuta SaBERT, no hace fact-checking y no calcula probabilidades.',
        ];
    }

    /** Hechos iniciales extraídos de señales observables del texto. */
    private function extraerHechos(string $texto): array
    {
        $hechos = [];
        $agregar = static function (string $hecho) use (&$hechos): void { $hechos[] = $hecho; };
        preg_match_all('/[!¡]/u', $texto, $exclamaciones);
        if (count($exclamaciones[0]) >= 2) $agregar('exclamaciones_repetidas');

        preg_match_all('/(?<!\p{L})\p{Lu}{4,}(?!\p{L})/u', $texto, $mayusculas);
        if (count($mayusculas[0]) >= 2) $agregar('mayusculas_excesivas');

        $hay = static fn (string $patron): bool => preg_match($patron, $texto) === 1;
        if ($hay('/\b(?:urgente|impactante|incre[ií]ble|secreto|conspiraci[oó]n)\b|no quieren/iu')) $agregar('titulo_sensacionalista');
        if ($hay('/\b(?:comparte|compartan|compartir|comp[aá]rtelo|reenv[ií]a|reenv[ií]en|dif[uú]ndelo)\b/iu')) $agregar('solicita_difusion');
        if ($hay('/fuentes? an[oó]nimas?|dicen que|se rumorea|seg[uú]n se dice/iu')) $agregar('fuente_anonima');
        if ($hay('/cura todo|remedio secreto|100\s*%\s*garantizado|soluci[oó]n milagrosa/iu')) $agregar('afirmacion_extraordinaria');
        if ($hay('/seg[uú]n (?:el|la|los|las)\s+\w+|publicad[oa] en\s+\w+|https?:\/\//iu')) {
            $agregar('cita_fuente');
        } else {
            $agregar('no_identifica_fuente');
        }
        if ($hay('/\b\d{1,2}\s+de\s+(?:enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|octubre|noviembre|diciembre)\b|\b\d{4}-\d{2}-\d{2}\b/iu')) $agregar('menciona_fecha');
        sort($hechos);
        return array_values(array_unique($hechos));
    }

    /** Selección por prioridad; mayor saliencia primero y, en empate, código. */
    private function agenda(array $hechos): array
    {
        $aplicables = [];
        foreach (self::REGLAS as $regla) {
            if (!in_array($regla['entonces'], $hechos, true)
                && count(array_diff($regla['si'], $hechos)) === 0) {
                $aplicables[] = $regla;
            }
        }
        usort($aplicables, static function ($a, $b): int {
            return ($b['prioridad'] <=> $a['prioridad']) ?: strcmp($a['id'], $b['id']);
        });
        return $aplicables;
    }

    /** Inferencia hacia adelante: reconocer, seleccionar y disparar reglas. */
    private function encadenarAdelante(array $hechos): array
    {
        $disparadas = [];
        while ($aplicables = $this->agenda($hechos)) {
            $regla = $aplicables[0];
            $hechos[] = $regla['entonces'];
            $disparadas[] = $regla;
        }
        sort($hechos);
        return [$hechos, $disparadas];
    }

    /** Demostración recursiva de la meta a partir de hechos iniciales. */
    private function encadenarAtras(string $meta, array $hechos, array $ruta): ?array
    {
        if (in_array($meta, $hechos, true)) return ['tipo'=>'hecho','nombre'=>$meta,'hijos'=>[]];
        if (in_array($meta, $ruta, true)) return null;
        $candidatas = array_values(array_filter(self::REGLAS, static fn($r) => $r['entonces'] === $meta));
        usort($candidatas, static fn($a, $b) => ($b['prioridad'] <=> $a['prioridad']) ?: strcmp($a['id'], $b['id']));
        $ruta[] = $meta;
        foreach ($candidatas as $regla) {
            $hijos = [];
            foreach ($regla['si'] as $antecedente) {
                $subprueba = $this->encadenarAtras($antecedente, $hechos, $ruta);
                if ($subprueba === null) continue 2;
                $hijos[] = $subprueba;
            }
            return ['tipo'=>'regla','nombre'=>$regla['id'],'conclusion'=>$meta,'hijos'=>$hijos];
        }
        return null;
    }

    /** BFS (cola) o DFS (pila). Estados = conjuntos de hechos. */
    private function buscarMeta(array $inicial, string $estrategia, int $maxEstados = 10000): array
    {
        $frontera = [['hechos'=>$inicial, 'camino'=>[]]];
        $visitados = [$this->claveEstado($inicial)=>true];
        $explorados = 0;
        while ($frontera && $explorados < $maxEstados) {
            $actual = $estrategia === 'anchura' ? array_shift($frontera) : array_pop($frontera);
            $explorados++;
            if (in_array(self::META, $actual['hechos'], true)) {
                return ['encontrada'=>true,'explorados'=>$explorados,'visitados'=>count($visitados),'pasos'=>count($actual['camino']),'recorrido'=>$actual['camino'],'limite_alcanzado'=>false];
            }
            $reglas = $this->agenda($actual['hechos']);
            if ($estrategia === 'profundidad') $reglas = array_reverse($reglas);
            foreach ($reglas as $regla) {
                $siguiente = $actual['hechos'];
                $siguiente[] = $regla['entonces'];
                $clave = $this->claveEstado($siguiente);
                if (isset($visitados[$clave])) continue;
                $visitados[$clave] = true;
                $camino = $actual['camino'];
                $camino[] = $regla;
                $frontera[] = ['hechos'=>$siguiente,'camino'=>$camino];
            }
        }
        return ['encontrada'=>false,'explorados'=>$explorados,'visitados'=>count($visitados),'pasos'=>0,'recorrido'=>[],'limite_alcanzado'=>count($frontera)>0];
    }

    private function claveEstado(array $hechos): string
    {
        sort($hechos);
        return implode('|', $hechos);
    }
}

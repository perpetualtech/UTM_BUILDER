/**
 * Puerto verbatim de `DICT_DEF` (UTP-Nomenclaturas.html, líneas 610-699) —
 * prosa descriptiva de campos y categorías del diccionario. A diferencia
 * de `seedDictionary.ts` (Anexo A/B, datos operativos que sí se editan en
 * Config), esto es documentación de referencia estática: nunca cambia y
 * no se deriva del diccionario editable, así que vive como constante del
 * frontend en vez de un endpoint (§7 no lo contempla — no hay ruta para
 * esto en el HTML tampoco, es contenido, no config).
 */

export interface DictFieldDef {
  key: string;
  label: string;
  desc: string;
}

export interface DictCategoryItem {
  key: string;
  label: string;
  desc: string;
}

export interface DictCategoryDef {
  title: string;
  desc: string;
  items: DictCategoryItem[];
}

export const dictDef: { fields: DictFieldDef[]; categories: DictCategoryDef[] } = {
  fields: [
    { key: "segmento", label: "Segmento", desc: "Público objetivo según etapa de vida. Define el tono, mensajes y restricción de pilares disponibles." },
    { key: "etapa", label: "Etapa de Funnel", desc: "Fase del embudo de marketing. Determina los medios, objetivos y tipos de campaña disponibles." },
    { key: "campus", label: "Campus / Sede", desc: "Sede física o modalidad geográfica a la que se dirige la campaña." },
    { key: "medio", label: "Medio (Plataforma)", desc: "Canal publicitario digital donde se publicarán los anuncios. Depende de la etapa del funnel." },
    { key: "objCamp", label: "Objetivo de Campaña", desc: "Meta de negocio que persigue la campaña: awareness, tráfico, conversión o venta." },
    { key: "objPlat", label: "Objetivo de Plataforma", desc: "Métrica de optimización interna de la plataforma (lo que el algoritmo persigue)." },
    { key: "tipoCamp", label: "Tipo de Campaña", desc: "Formato o mecánica de la campaña dentro de la plataforma (Search, Video, PMAX, LeadAds, etc.)." },
    { key: "pilar", label: "Pilar Estratégico", desc: 'Eje de comunicación de la propuesta de valor UTP. El pilar "empleabilidad" es exclusivo de Jóvenes.' },
    { key: "edad", label: "Edad (Conjunto)", desc: "Rango de edad de la audiencia. a=adultos, j=jóvenes, 1=tramo inferior, 2=tramo superior. Combinables: a1-a2, j1-j2." },
    { key: "ubicacion", label: "Ubicación", desc: "Zona geográfica de segmentación para el conjunto de anuncios." },
    { key: "facultad", label: "Facultad", desc: "Área académica de enfoque del conjunto. Permite controlar frecuencia y relevar por facultad. Algunos campus tienen restricción de facultad." },
    { key: "senal", label: "Tipo de Señal", desc: "Estrategia de targeting del conjunto. Combinaciones de broad, lookalike (lal) y remarketing (rmkt)." },
    { key: "detalle", label: "Detalle de Audiencia", desc: "Campo libre para describir la audiencia específica del conjunto. Permite identificar variantes sin cambiar la estructura." },
    { key: "formato", label: "Formato de Anuncio", desc: "Tipo de pieza creativa. Determina el formato de producción y las especificaciones técnicas de entrega." },
    { key: "nombre", label: "Concepto Creativo", desc: 'Nombre del concepto o campaña de comunicación (ej. "lo-que-el-mar-se-llevo", "monarca"). Identifica el creative set.' },
    { key: "motivo", label: "Motivo", desc: "Ángulo comunicacional del anuncio: qué razón o historia usa para conectar (testimonial, beneficios, lifestyle, etc.)." },
    { key: "mensaje", label: "Mensaje Clave", desc: "Copy o concepto principal del anuncio para identificación interna. Corresponde a la propuesta de valor o claim." },
    { key: "carrera", label: "Carrera", desc: 'Programa académico específico al que apunta el anuncio. "no-carreras" indica que el anuncio es genérico.' },
    { key: "fecha", label: "Fecha de Activación", desc: "Mes y año de publicación del anuncio en formato abreviado. Ej: ene26 = enero 2026." },
  ],
  categories: [
    {
      title: "Etapas del Funnel",
      desc: "Define en qué momento del proceso de decisión se encuentra el prospecto.",
      items: [
        { key: "upper", label: "Upper Funnel", desc: "Awareness y reconocimiento de marca. Objetivos: Alcance, Vistas, CPM. Sin restricción fuerte de plataforma." },
        { key: "middle", label: "Middle Funnel", desc: "Consideración e interés. Objetivos: Tráfico, Leads cualificados, WPP. Incluye estrategias RMKT." },
        { key: "lower", label: "Lower Funnel", desc: "Conversión directa. Objetivos: LeadAds, LeadWeb, Inscritos. Menor gasto disponible, máxima intención." },
      ],
    },
    {
      title: "Pilares Estratégicos UTP",
      desc: "Los cuatro ejes de comunicación de la propuesta de valor de UTP.",
      items: [
        { key: "calidad", label: "Calidad", desc: "Excelencia académica, docentes con experiencia real, reconocimiento y acreditaciones. Aplica a Adultos y Jóvenes." },
        { key: "accesibilidad", label: "Accesibilidad", desc: "Horarios flexibles, modalidades online/presencial, cuotas asequibles y sedes accesibles. Aplica a Adultos y Jóvenes." },
        { key: "orgullo", label: "Orgullo", desc: "Identidad y pertenencia UTP, comunidad alumni, logros institucionales. Aplica a Adultos y Jóvenes." },
        { key: "empleabilidad", label: "Empleabilidad", desc: "Inserción laboral, convenios con empresas, casos de éxito profesional. Exclusivo del segmento Jóvenes." },
      ],
    },
    {
      title: "Medios Activos",
      desc: "Plataformas publicitarias en el plan de medios digital UTP.",
      items: [
        { key: "Meta", label: "Meta Ads", desc: "Facebook e Instagram Ads. Mayor volumen de leads, LeadAds y conversiones web. Aplica a todas las etapas." },
        { key: "Tiktok", label: "TikTok Ads", desc: "Formatos de video corto. Principalmente upper y middle funnel para segmento Jóvenes." },
        { key: "DV360", label: "DV360", desc: "Display & Video 360 (Google). Compra programática para awareness masivo. Upper funnel." },
        { key: "LinkedIn", label: "LinkedIn Ads", desc: "Red profesional. Ideal para adultos, postgrado, targeting por empresa/cargo. Todas las etapas." },
        { key: "GoogleAds", label: "Google Ads", desc: "Search, PMAX, Display, YouTube. Captura demanda activa con intención de búsqueda. Middle y lower." },
      ],
    },
    {
      title: "Señales de Audiencia",
      desc: "Estrategias de targeting disponibles para configurar conjuntos de anuncios.",
      items: [
        { key: "broad", label: "Broad", desc: "Sin restricción de audiencia. El algoritmo optimiza automáticamente hacia los mejores usuarios." },
        { key: "lal", label: "Lookalike (LAL)", desc: "Audiencia similar a una base conocida: inscritos, leads cualificados, base CRM." },
        { key: "rmkt", label: "Remarketing (RMKT)", desc: "Usuarios que ya interactuaron con UTP: visitas a web, engagement, base de datos CRM." },
        { key: "intereses", label: "Intereses", desc: "Segmentación por categorías de interés declaradas o inferidas por la plataforma." },
        { key: "int-lal", label: "Intereses + LAL", desc: "Combina intereses declarados con audiencias similares para mayor precisión." },
        { key: "broad-lal", label: "Broad + LAL", desc: "Mezcla de amplitud y similaridad para testing A/B controlado." },
        { key: "int-rmkt", label: "Intereses + RMKT", desc: "Intereses superpuestos con remarketing para reforzar relevancia." },
        { key: "lal-rmkt", label: "LAL + RMKT", desc: "Doble señal: lookalike más remarketing para audiencias más cualificadas." },
        { key: "int-lal-rmkt", label: "Intereses + LAL + RMKT", desc: "Triple combinación para audiencias de alta intención y calificación." },
        { key: "int-adv", label: "Intereses Avanzados", desc: "Intereses con advantage+ targeting o custom audiences avanzadas de la plataforma." },
      ],
    },
    {
      title: "Formatos de Anuncio",
      desc: "Tipos de pieza creativa disponibles por plataforma.",
      items: [
        { key: "video", label: "Video", desc: "Video de 6-30s para feed o stories. Meta, TikTok, LinkedIn, DV360." },
        { key: "carrusel", label: "Carrusel", desc: "Múltiples tarjetas deslizables. Ideal para mostrar varios programas o beneficios." },
        { key: "ppl", label: "PPL", desc: "Post Page Like. Formato de engagement para crecer la página de Facebook." },
        { key: "ppv", label: "PPV", desc: "Post Page View. Formato de tráfico hacia la fan page." },
        { key: "collection", label: "Collection", desc: "Colección de Meta: hero video + grid de productos/programas. Solo Mobile." },
        { key: "catalogo", label: "Catálogo", desc: "Dynamic Ads con catálogo de productos. Ideal para RMKT dinámico de programas." },
        { key: "sparkad", label: "SparkAd", desc: "Formato de TikTok que impulsa un organic post como anuncio pagado." },
        { key: "rsa", label: "RSA", desc: "Responsive Search Ad de Google. Titulares y descripciones combinados por el algoritmo." },
        { key: "instream", label: "Instream", desc: "Video antes/durante otro video (YouTube, DV360). Skippable o non-skippable." },
        { key: "bumper", label: "Bumper", desc: "Video de 6 segundos no skippable en YouTube. Alta frecuencia a bajo CPM." },
        { key: "short", label: "Short", desc: "YouTube Shorts / video vertical de 15-60s. Formato nativo corto." },
        { key: "banner", label: "Banner", desc: "Display estático o animado (GIF/HTML5). DV360, Google Display Network." },
        { key: "youtube video", label: "YouTube Video", desc: "Video largo (30s+) en YouTube: TrueView in-stream skippable." },
      ],
    },
  ],
};

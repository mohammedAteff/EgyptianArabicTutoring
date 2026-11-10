// Copyright (c) 2026 Mohamed Magdy. Adapted with owner-confirmed permission.
// Source: https://github.com/mohammedAteff/6word/blob/7d20671ea32efc6c3571b7d1e2260204b9eba49a/src/data/spanishWordBanks.ts
/**
 * Spanish language bank for the public game.
 *
 * The curated bank keeps Spanish playable offline; the optional AI source can
 * add new combinations when a server key is configured.
 */
export const SPANISH_ROUND_BANK = {
  easy: [
    ["viaje","aeropuerto","pasaporte","sello","recuerdo","hogar"],
    ["mar","barco","capitán","tormenta","valor","salvación"],
    ["escuela","maestra","libro","pregunta","respuesta","curiosidad"],
    ["jardín","semilla","tierra","agua","flor","sonrisa"],
    ["cocina","pan","mesa","invitado","bienvenida","amistad"],
    ["mercado","cesta","manzana","precio","compra","cena"],
    ["tren","estación","billete","asiento","charla","amigo"],
    ["hospital","médica","cuidado","recuperación","esperanza","vida"],
    ["música","piano","melodía","recuerdo","nostalgia","hogar"],
    ["granja","campo","lluvia","cosecha","gratitud","abundancia"],
    ["cumpleaños","vela","deseo","sueño","esfuerzo","éxito"],
    ["montaña","sendero","cima","esfuerzo","logro","confianza"],
    ["biblioteca","estante","novela","héroe","valentía","cambio"],
    ["ventana","cortina","sol","polvo","color","habitación"],
    ["regalo","lazo","caja","nota","gracias","sorpresa"]
  ],
  medium: [
    ["mercado","moneda","elección","riesgo","experiencia","crecimiento"],
    ["coche","viaje","desconocido","carta","recuerdo","regreso"],
    ["fotografía","retrato","rostro","historia","secreto","confianza"],
    ["río","puente","viajero","decisión","valor","comienzo"],
    ["oficina","reunión","idea","objeción","diálogo","éxito"],
    ["teatro","actor","telón","silencio","tensión","aplauso"],
    ["teléfono","mensaje","cita","espera","ansiedad","reencuentro"],
    ["hotel","llave","habitación","ventana","ciudad","aventura"],
    ["reloj","plazo","retraso","disculpa","honestidad","respeto"],
    ["pueblo","abuela","relato","sabiduría","decisión","futuro"],
    ["museo","estatua","historia","pregunta","identidad","raíces"],
    ["carrera","rival","derrota","lección","persistencia","victoria"],
    ["faro","niebla","sirena","naufragio","búsqueda","rescate"],
    ["archivo","mapa","fragmento","origen","pista","descubrimiento"],
    ["orquesta","director","pausa","ruido","armonía","encanto"]
  ],
  hard: [
    ["observatorio","estrella","constelación","mito","pregunta","asombro"],
    ["taller","plano","martillo","marco","paciencia","oficio"],
    ["invernadero","brote","vidrio","calor","sequía","adaptación"],
    ["código","revisión","error","colaboración","lanzamiento","confianza"],
    ["isla","brújula","corriente","expedición","peligro","regreso"],
    ["subasta","herencia","oferta","incertidumbre","elección","consecuencia"],
    ["migración","ala","clima","ruta","descanso","supervivencia"],
    ["manuscrito","tinta","traducción","margen","autor","legado"],
    ["desfiladero","eco","escalador","cuerda","miedo","coraje"],
    ["circuito","chispa","corriente","avería","diagnóstico","giro"],
    ["invierno","deshielo","inundación","saco","vecino","solidaridad"],
    ["tribunal","prueba","testigo","duda","veredicto","misericordia"],
    ["bosque","incendio","humo","sendero","desvío","destino"],
    ["puente","grieta","reparación","plazo","equipo","confianza"],
    ["galería","escultura","sombra","significado","reflexión","memoria"]
  ],
  extreme: [
    ["eclipse","oráculo","predicción","augurio","testigo","ajuste de cuentas"],
    ["palimpsesto","borrado","borde","código","lector","renacimiento"],
    ["catacumba","vela","inscripción","descenso","misterio","regreso"],
    ["física cuántica","paradoja","medición","duda","teoría","revisión"],
    ["monzón","desfiladero","puente","derrumbe","desvío","destino"],
    ["torre del reloj","cuervo","carta","coartada","silencio","veredicto"],
    ["abismo","sonar","profundidad","señal","superficie","contacto"],
    ["banco de semillas","sequía","genoma","agricultor","promesa","futuro"],
    ["máscara","espejo","impostor","prueba","revelación","identidad"],
    ["reloj de sol","sombra","imperio","ruina","arqueóloga","recuerdo"],
    ["trueno","telégrafo","frontera","mensaje","tregua","paz"],
    ["laberinto","hilo","minotauro","elección","salida","patria"],
    ["profecía","acertijo","templo","sacrificio","esperanza","destino"],
    ["piel de gallina","susurro","grabadora","prueba","negación","confesión"],
    ["teatro de sombras","luz","figura","escenario","giro","verdad"]
  ]
};

export const SPANISH_WILD_BANK = {
  places:["aeropuerto","pueblo","hospital","estadio","biblioteca","patio escolar","faro","mercado cubierto","terraza","sendero","estación","ferrocarril","observatorio","granja","puerto","isla","cascada","parque infantil","taller","galería","teatro","puente","camping","callejón","ayuntamiento","panadería","museo","piscina","zoológico","iglesia","castillo","túnel","parque","cine","universidad","farmacia","camino de montaña","ferry","cueva","plaza"],
  people:["piloto","maestra","cocinero","dentista","ingeniera","músico","bailarina","científico","periodista","artista","jardinera","marinero","guía","detective","atleta","diseñadora","fontanero","carpintera","estudiante","vecino","alcaldesa","cajero","editora","voluntario","médica","bombero","fotógrafa","investigador","panadera","jueza","mecánico","arquitecta","pintor","cantante","entrenadora","programador","vendedora","abogado","enfermera","capitán"],
  objects:["cuaderno","brújula","linterna","maleta","llave","botella","espejo","paraguas","mochila","silbato","regla","cámara","escalera","almohada","manta","cuchara","taza","cuerda","cubo","rompecabezas","vela","botón","reloj","postal","diario","sobre","tijeras","martillo","guitarra","tablero de ajedrez","tetera","farol","gafas","cartera","bicicleta","pasaporte","bandera","globo terráqueo","prismáticos","despertador"],
  nature:["brisa","trueno","nube","atardecer","amanecer","bosque","pradera","desierto","glaciar","volcán","orilla del río","cascada","océano","desfiladero","colina","hoja","pino","helecho","canto de pájaro","luz de luna","tormenta","llovizna","niebla","luz solar","relámpago","arroyo","musgo","hiedra","roble","arce","sauce","arcoíris","rocío","escarcha","guijarro","luz estelar","acantilado","cueva","costa isleña","campo de flores"],
  food:["manzana","plátano","naranja","pera","uva","melón","melocotón","limón","arroz","frijol","sopa","ensalada","pasta","crepe","gofre","magdalena","miel","mantequilla","aceituna","tomate","pepino","cebolla","ajo","pizza","pan","queso","yogur","patata","zanahoria","cereza","ciruela","albaricoque","higo","almendra","nuez","mermelada","chocolate","pastel","pretzel","helado"],
  technology:["móvil","teclado","ratón","monitor","router","auriculares","micrófono","batería","cable","sensor","dron","robot","cámara","proyector","altavoz","navegador","contraseña","servidor en la nube","señal","píxel","programa","red","copia de seguridad","actualización","tableta","portátil","impresora","escáner","módem","microchip","cámara web","láser","holograma","consola","joystick","reloj inteligente","base de datos","cortafuegos","chatbot","algoritmo"],
  feelings:["calma","curiosidad","orgullo","vergüenza","alivio","preocupación","sorpresa","alegría","soledad","amabilidad","paciencia","envidia","valentía","confianza","duda","anhelo","asombro","gratitud","vergüenza ajena","seguridad","ternura","energía","consuelo","determinación","esperanza","enfado","tristeza","entusiasmo","aburrimiento","remordimiento","amor","admiración","frustración","paz","calor","miedo","serenidad","fascinación","compasión","expectativa"],
  actions:["ir","subir","dibujar","pintar","cantar","bailar","escuchar","preguntar","responder","construir","reparar","plantar","medir","elegir","cambiar","recordar","invitar","proteger","descubrir","imaginar","practicar","viajar","susurrar","celebrar","correr","nadar","cocinar","leer","escribir","reír","llorar","buscar","esperar","abrir","cerrar","lanzar","atrapar","soñar","compartir","aprender"],
  concepts:["libertad","justicia","azar","memoria","sueño","lenguaje","cultura","patrón","equilibrio","propósito","curiosidad","misterio","cambio","futuro","pasado","comienzo","final","promesa","elección","aventura","respeto","esperanza","sabiduría","responsabilidad","fantasía","fracaso","progreso","identidad","comunidad","verdad","orden","caos","belleza","conocimiento","destino","patria","perdón","pensamiento","paciencia","confianza"],
  animals:["zorro","lobo","conejo","caballo","vaca","cerdo","gallina","pato","ganso","tortuga","delfín","ballena","tiburón","pulpo","pingüino","búho","loro","gorrión","abeja","mariposa","ardilla","erizo","camello","burro","león","tigre","oso","ciervo","cabra","oveja","mono","jirafa","cebra","canguro","koala","nutria","foca","pavo real","águila","halcón"],
  work:["ingeniero","maestra","enfermero","diseñadora","autor","editora","albañil","agricultora","conductor","entrenador","abogada","contable","artista","investigador","cocinera","gerente","reunión","plazo","proyecto","sueldo","turno","cliente","oficina","pausa","tarea","informe","contrato","planificación","equipo","carrera","herramienta","fábrica","tienda","cita","llamada","correo electrónico","presentación","entrevista","encargo","compañero"],
  time:["martes","miércoles","jueves","viernes","amanecer","crepúsculo","mañana","tarde","medianoche","fin de semana","festivo","estación","minuto","instante","hoy","pasado mañana","ayer","pronto","nunca","a veces","a menudo","primavera","verano","invierno","otoño","lunes","sábado","domingo","mediodía","noche","semana","mes","año","enero","abril","diciembre","ahora","después","siempre","al rato"]
};

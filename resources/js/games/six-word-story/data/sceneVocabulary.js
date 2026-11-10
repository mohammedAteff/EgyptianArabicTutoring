/** Original scene vocabulary. Columns: category, English, Egyptian Arabic, Arabic, French, German, Spanish, Italian, Portuguese. */
export const SCENE_LANGUAGE_ORDER = ['en', 'ar-eg', 'ar', 'fr', 'de', 'es', 'it', 'pt'];
// Egyptian register: everyday Cairene wording. Usage references: IFAO and Living Arabic.
// https://www.ifao.egnet.net/bases/verbeseg/about | https://livingarabic.com/en/help
export const SCENE_GROUPS = [
    {name: 'Coast', scenes: [
        {name: 'Beach', entries: `objects|bucket|جردل|دلو|seau|Eimer|cubo|secchio|balde
objects|spade|جاروف|مجرفة|pelle|Schaufel|pala|paletta|pá
objects|sandcastle|قصر من الرمل|قلعة رملية|château de sable|Sandburg|castillo de arena|castello di sabbia|castelo de areia
nature|seashell|قوقعة|صدفة بحرية|coquillage|Muschel|concha marina|conchiglia|concha
actions|dig|احفر|احفر|creuser|graben|cavar|scavare|cavar
nature|footprint|أثر رجل|طبعة قدم|empreinte|Fußabdruck|huella|impronta|pegada
objects|beach towel|فوطة بحر|منشفة شاطئ|serviette de plage|Strandtuch|toalla de playa|telo da mare|toalha de praia
objects|sunhat|برنيطة|قبعة للشمس|chapeau de soleil|Sonnenhut|sombrero de sol|cappello da sole|chapéu de sol
objects|sunscreen|صن بلوك|واقي شمس|crème solaire|Sonnencreme|protector solar|crema solare|protetor solar
objects|parasol|شمسية بحر|مظلة شاطئ|parasol|Sonnenschirm|sombrilla|ombrellone|guarda-sol
objects|deckchair|كرسي بحر|كرسي استلقاء|transat|Liegestuhl|tumbona|sdraio|espreguiçadeira
actions|sunbathe|اتشمس|تشمس|bronzer|sonnenbaden|tomar el sol|prendere il sole|tomar sol
people|lifeguard|منقذ بحر|منقذ سباحة|maître-nageur|Rettungsschwimmer|socorrista|bagnino|salva-vidas
objects|warning flag|راية تحذير|علم تحذير|drapeau d'alerte|Warnflagge|bandera de aviso|bandiera di pericolo|bandeira de aviso
objects|lifebuoy|طوق نجاة|طوق نجاة|bouée de sauvetage|Rettungsring|aro salvavidas|salvagente|boia de salvamento
people|swimmer|سباح|سباح|nageur|Schwimmer|nadador|nuotatore|nadador
actions|float|خلّيك عايم|اطفُ|flotter|treiben|flotar|galleggiare|flutuar
feelings|refreshment|انتعاش|انتعاش|fraîcheur|Erfrischung|frescura|freschezza|frescor`},
        {name: 'Sailing', entries: `objects|mast|صاري المركب|صارية|mât|Mast|mástil|albero maestro|mastro
objects|rudder|دفة|دفة|gouvernail|Ruder|timón|timone|leme
objects|anchor|هلب|مرساة|ancre|Anker|ancla|ancora|âncora
objects|rope knot|عقدة في الحبل|عقدة حبل|nœud marin|Seemannsknoten|nudo marinero|nodo marinaro|nó de marinheiro
actions|hoist|ارفع|ارفع|hisser|hissen|izar|issare|içar
nature|tailwind|هوا من ورا|ريح مواتية|vent arrière|Rückenwind|viento de popa|vento in poppa|vento de popa
places|cabin|كابينة المركب|مقصورة السفينة|cabine|Kajüte|camarote|cabina|cabine
objects|hammock|سرير شبك|أرجوحة شبكية|hamac|Hängematte|hamaca|amaca|rede de descanso
objects|life jacket|سترة نجاة|سترة نجاة|gilet de sauvetage|Schwimmweste|chaleco salvavidas|giubbotto salvagente|colete salva-vidas
people|navigator|ملاح|ملاح|navigateur|Navigator|navegante|navigatore|navegador
technology|sonar|سونار|سونار|sonar|Sonar|sonar|sonar|sonar
concepts|bearing|اتجاه البوصلة|اتجاه البوصلة|cap|Peilung|rumbo|rilevamento|rumo
nature|swell|موج عالي|موج متطاول|houle|Dünung|mar de fondo|onda lunga|ondulação
actions|steer|وجّه المركب|وجّه السفينة|gouverner|steuern|gobernar|timonare|pilotar
objects|logbook|دفتر الرحلة|سجل الرحلة|journal de bord|Logbuch|cuaderno de bitácora|giornale di bordo|diário de bordo
time|watch shift|وردية حراسة|نوبة حراسة|quart|Wachschicht|guardia|turno di guardia|turno de vigia
feelings|seasickness|دوخة البحر|دوار البحر|mal de mer|Seekrankheit|mareo|mal di mare|enjoo
concepts|safe passage|رحلة آمنة|عبور آمن|passage sûr|sichere Passage|paso seguro|passaggio sicuro|passagem segura`},
        {name: 'Harbour', entries: `places|quay|رصيف المينا|رصيف ميناء|quai|Kai|muelle|banchina|cais
places|breakwater|حاجز أمواج|حاجز أمواج|digue|Wellenbrecher|rompeolas|frangiflutti|quebra-mar
places|dockyard|ترسانة|ترسانة بحرية|chantier naval|Werft|astillero|cantiere navale|estaleiro
people|dockworker|عامل في المينا|عامل ميناء|docker|Hafenarbeiter|estibador|portuale|estivador
technology|cargo crane|ونش شحن|رافعة شحن|grue portuaire|Hafenkran|grúa portuaria|gru portuale|guindaste portuário
objects|shipping container|حاوية شحن|حاوية شحن|conteneur|Frachtcontainer|contenedor|container|contêiner
people|customs officer|موظف جمارك|موظف جمارك|douanier|Zöllner|aduanero|doganiere|alfandegário
work|cargo manifest|كشف حمولة|بيان حمولة|manifeste de cargaison|Ladungsverzeichnis|manifiesto de carga|manifesto di carico|manifesto de carga
objects|security seal|ختم الشحنة|ختم أمني|scellé|Plombe|precinto|sigillo|lacre
actions|inspect|فتّش|فتّش|inspecter|prüfen|inspeccionar|ispezionare|inspecionar
concepts|clearance|تخليص جمركي|تخليص جمركي|autorisation|Freigabe|autorización|autorizzazione|autorização
work|shipment|شحنة|شحنة|expédition|Sendung|envío|spedizione|remessa
places|ferry terminal|مرسى المعدية|محطة عبّارات|gare maritime|Fährterminal|terminal de ferris|terminal traghetti|terminal de balsas
objects|gangway|سلم المركب|سلم الصعود|passerelle|Gangway|pasarela|passerella|passarela
actions|disembark|انزل من المركب|غادر السفينة|débarquer|von Bord gehen|desembarcar|sbarcare|desembarcar
people|passenger|راكب|راكب|passager|Fahrgast|pasajero|passeggero|passageiro
time|arrival time|ميعاد الوصول|موعد الوصول|heure d'arrivée|Ankunftszeit|hora de llegada|ora di arrivo|hora de chegada
feelings|reunion joy|فرحة اللمة|فرحة اللقاء|joie des retrouvailles|Wiedersehensfreude|alegría del reencuentro|gioia del ritorno|alegria do reencontro`},
        {name: 'Fishing', entries: `objects|fishing rod|سنارة|صنارة صيد|canne à pêche|Angelrute|caña de pescar|canna da pesca|vara de pesca
objects|fishhook|سن السنارة|خطاف صيد|hameçon|Angelhaken|anzuelo|amo|anzol
objects|bait|طعم صيد|طعم|appât|Köder|cebo|esca|isca
objects|fishing net|شبكة صيد|شبكة صيد|filet de pêche|Fischernetz|red de pesca|rete da pesca|rede de pesca
actions|cast|ارمي السنارة|ألقِ الصنارة|lancer la ligne|auswerfen|lanzar el anzuelo|lanciare la lenza|lançar a linha
people|fisherman|صياد سمك|صياد أسماك|pêcheur|Fischer|pescador|pescatore|pescador
animals|mullet|بوري|سمك البوري|mulet|Meeräsche|mújol|cefalo|tainha
animals|sardine|سردينة|سردين|sardine|Sardine|sardina|sardina|sardinha
animals|tuna|تونة|تونة|thon|Thunfisch|atún|tonno|atum
animals|sea bass|قاروص|قاروص|bar|Wolfsbarsch|lubina|branzino|robalo
objects|fish scale|قشر سمك|قشور السمك|écaille|Fischschuppe|escama|squama|escama
actions|release|سيب|أطلق|relâcher|freilassen|soltar|rilasciare|soltar
places|fish market|سوق السمك|سوق الأسماك|marché aux poissons|Fischmarkt|pescadería|mercato del pesce|mercado de peixe
objects|cool box|صندوق تلج|صندوق تبريد|glacière|Kühlbox|nevera portátil|borsa frigo|caixa térmica
food|fish fillet|فيليه سمك|شريحة سمك|filet de poisson|Fischfilet|filete de pescado|filetto di pesce|filé de peixe
actions|grill|اشوي|اشوِ|griller|grillen|asar|grigliare|grelhar
food|marinade|تتبيلة|تتبيلة|marinade|Marinade|adobo|marinatura|marinada
feelings|satisfaction|رضا|رضا|satisfaction|Zufriedenheit|satisfacción|soddisfazione|satisfação`},
        {name: 'Diving', entries: `objects|flipper|زعانف غطس|زعنفة غوص|palme|Schwimmflosse|aleta|pinna|nadadeira
objects|wetsuit|بدلة غطس|بدلة غوص|combinaison de plongée|Neoprenanzug|traje de neopreno|muta|roupa de mergulho
technology|diving cylinder|أنبوبة غطس|أسطوانة غوص|bouteille de plongée|Tauchflasche|botella de buceo|bombola da sub|cilindro de mergulho
objects|diving mask|نظارة غطس|قناع غوص|masque de plongée|Tauchmaske|máscara de buceo|maschera da sub|máscara de mergulho
people|diver|غواص|غواص|plongeur|Taucher|buzo|subacqueo|mergulhador
actions|submerge|اغطس|اغطس|s'immerger|abtauchen|sumergirse|immergersi|submergir
nature|coral reef|شعاب مرجانية|شعاب مرجانية|récif corallien|Korallenriff|arrecife de coral|barriera corallina|recife de coral
animals|seahorse|حصان بحر|فرس البحر|hippocampe|Seepferdchen|caballito de mar|cavalluccio marino|cavalo-marinho
animals|clownfish|سمكة المهرج|سمكة المهرج|poisson-clown|Clownfisch|pez payaso|pesce pagliaccio|peixe-palhaço
animals|manta ray|شيطان البحر|شيطان البحر|raie manta|Mantarochen|mantarraya|manta|arraia-manta
nature|air bubble|فقاعة هوا|فقاعة هواء|bulle d'air|Luftblase|burbuja de aire|bolla d'aria|bolha de ar
concepts|depth|عمق|عمق|profondeur|Tiefe|profundidad|profondità|profundidade
places|shipwreck|حطام مركب|حطام سفينة|épave|Schiffswrack|naufragio|relitto|naufrágio
objects|porthole|شباك المركب|نافذة دائرية|hublot|Bullauge|ojo de buey|oblò|vigia
objects|treasure coin|عملة قديمة|عملة أثرية|pièce ancienne|Schatzmünze|moneda antigua|moneta antica|moeda antiga
actions|surface|اطلع للسطح|اصعد إلى السطح|remonter|auftauchen|salir a la superficie|riemergere|emergir
feelings|awe|رهبة|رهبة|émerveillement|Ehrfurcht|asombro|stupore|deslumbramento
concepts|discovery|اكتشاف|اكتشاف|découverte|Entdeckung|descubrimiento|scoperta|descoberta`},
        {name: 'Seaside wildlife', entries: `animals|seagull|نورس|نورس|mouette|Möwe|gaviota|gabbiano|gaivota
animals|pelican|بجعة|بجع|pélican|Pelikan|pelícano|pellicano|pelicano
animals|cormorant|غراب بحر|غاق|cormoran|Kormoran|cormorán|cormorano|corvo-marinho
animals|crab|كابوريا|سلطعون|crabe|Krabbe|cangrejo|granchio|caranguejo
animals|hermit crab|سلطعون ناسك|سلطعون ناسك|bernard-l'ermite|Einsiedlerkrebs|cangrejo ermitaño|paguro|caranguejo-eremita
animals|starfish|نجمة بحر|نجم البحر|étoile de mer|Seestern|estrella de mar|stella marina|estrela-do-mar
animals|sea urchin|قنفد بحر|قنفذ البحر|oursin|Seeigel|erizo de mar|riccio di mare|ouriço-do-mar
animals|jellyfish|قنديل بحر|قنديل البحر|méduse|Qualle|medusa|medusa|água-viva
animals|seal|فقمة|فقمة|phoque|Robbe|foca|foca|foca
animals|walrus|حيوان الفظ|حيوان الفظ|morse|Walross|morsa|tricheco|morsa
animals|puffin|طائر البفن|طائر البفن|macareux|Papageitaucher|frailecillo|pulcinella di mare|papagaio-do-mar
nature|tidal pool|بركة ميّه بين الصخور|بركة مدّية|flaque de marée|Gezeitentümpel|poza de marea|pozza di marea|poça de maré
nature|salt marsh|سبخة|سبخة|marais salé|Salzwiese|marisma|palude salmastra|sapal
nature|sand dune|تل رمل|كثيب رملي|dune|Sanddüne|duna|duna|duna
nature|driftwood|خشب طافي|خشب طافٍ|bois flotté|Treibholz|madera flotante|legno alla deriva|madeira flutuante
actions|migrate|هاجر|هاجر|migrer|wandern|migrar|migrare|migrar
concepts|habitat|موطن طبيعي|موطن طبيعي|habitat|Lebensraum|hábitat|habitat|habitat
concepts|conservation|حماية الطبيعة|حماية الطبيعة|préservation|Naturschutz|conservación|conservazione|conservação`},
    ]},
    {name: 'Travel', scenes: [
        {name: 'Railway', entries: `places|railway platform|رصيف قطر|رصيف قطار|quai de gare|Bahnsteig|andén|banchina ferroviaria|plataforma ferroviária
objects|railway track|قضبان|قضبان السكة الحديدية|rails|Schienen|vías|rotaie|trilhos
technology|locomotive|جرار القطر|قاطرة|locomotive|Lokomotive|locomotora|locomotiva|locomotiva
people|train conductor|كمسري|محصل تذاكر|contrôleur|Zugbegleiter|revisor|controllore|cobrador
objects|ticket punch|خرامة تذاكر|خرّامة تذاكر|poinçon|Lochzange|perforadora de billetes|pinza foratrice|furador de bilhetes
work|timetable|جدول مواعيد|جدول مواعيد|horaire|Fahrplan|horario|orario|horário
objects|sleeper berth|سرير في القطر|سرير في القطار|couchette|Schlafkoje|litera|cuccetta|beliche
places|dining carriage|عربية الأكل|عربة مطعم|wagon-restaurant|Speisewagen|coche restaurante|vagone ristorante|vagão-restaurante
objects|luggage rack|رف شنط|رف أمتعة|porte-bagages|Gepäckablage|portaequipajes|portabagagli|bagageiro
actions|change trains|غيّر القطر|بدّل القطار|changer de train|umsteigen|hacer transbordo|cambiare treno|fazer baldeação
time|delay|تأخير|تأخير|retard|Verspätung|retraso|ritardo|atraso
feelings|impatience|قلة صبر|نفاد الصبر|impatience|Ungeduld|impaciencia|impazienza|impaciência
places|railway crossing|مزلقان القطر|معبر سكة حديد|passage à niveau|Bahnübergang|paso a nivel|passaggio a livello|passagem de nível
objects|signal box|كشك إشارات|برج إشارات|poste d'aiguillage|Stellwerk|puesto de señales|cabina di segnalazione|cabine de sinalização
technology|brake|فرامل|مكبح|frein|Bremse|freno|freno|freio
actions|depart|اتحرك|انطلق|partir|abfahren|partir|partire|partir
concepts|destination|آخر محطة|وجهة|destination|Reiseziel|destino|destinazione|destino
feelings|anticipation|لهفة|ترقب|attente impatiente|Vorfreude|expectación|trepidazione|expectativa`},
        {name: 'Airport', entries: `places|runway|مدرج طيارات|مدرج طائرات|piste d'atterrissage|Startbahn|pista de aterrizaje|pista di atterraggio|pista de pouso
places|control tower|برج مراقبة|برج مراقبة|tour de contrôle|Kontrollturm|torre de control|torre di controllo|torre de controle
people|flight attendant|مضيفة طيران|مضيفة طيران|hôtesse de l'air|Flugbegleiterin|azafata|assistente di volo|comissária de bordo
objects|boarding pass|كارت ركوب الطيارة|بطاقة صعود الطائرة|carte d'embarquement|Bordkarte|tarjeta de embarque|carta d'imbarco|cartão de embarque
objects|seat belt|حزام أمان|حزام أمان|ceinture de sécurité|Sicherheitsgurt|cinturón de seguridad|cintura di sicurezza|cinto de segurança
actions|take off|الطيارة تقلع|أقلع|décoller|abheben|despegar|decollare|decolar
objects|overhead locker|رف الشنط|خزانة الأمتعة|coffre à bagages|Gepäckfach|compartimento superior|cappelliera|compartimento de bagagem
objects|carry-on bag|شنطة يد|حقيبة يد|bagage cabine|Handgepäck|equipaje de mano|bagaglio a mano|bagagem de mão
food|airline meal|وجبة الطيارة|وجبة على الطائرة|repas à bord|Bordmahlzeit|comida de avión|pasto a bordo|refeição de bordo
technology|autopilot|طيار آلي|طيار آلي|pilote automatique|Autopilot|piloto automático|pilota automatico|piloto automático
concepts|altitude|ارتفاع|ارتفاع|altitude|Flughöhe|altitud|altitudine|altitude
feelings|jet lag|تعب فرق التوقيت|إرهاق فرق التوقيت|décalage horaire|Jetlag|desfase horario|jet lag|jet lag
places|baggage claim|استلام الشنط|استلام الأمتعة|retrait des bagages|Gepäckausgabe|recogida de equipajes|ritiro bagagli|retirada de bagagem
objects|baggage carousel|سير الشنط|سير الأمتعة|tapis à bagages|Gepäckband|cinta de equipajes|nastro bagagli|esteira de bagagem
actions|land|الطيارة تنزل|اهبط|atterrir|landen|aterrizar|atterrare|aterrissar
work|flight number|رقم الرحلة|رقم الرحلة|numéro de vol|Flugnummer|número de vuelo|numero del volo|número do voo
time|layover|ترانزيت|توقف بين الرحلات|escale|Zwischenaufenthalt|escala|scalo|escala
concepts|time zone|منطقة زمنية|منطقة زمنية|fuseau horaire|Zeitzone|zona horaria|fuso orario|fuso horário`},
        {name: 'Road journey', entries: `places|motorway|طريق سريع|طريق سريع|autoroute|Autobahn|autopista|autostrada|autoestrada
places|roundabout|ميدان|دوار مروري|rond-point|Kreisverkehr|rotonda|rotatoria|rotatória
objects|road sign|يافطة طريق|لافتة مرورية|panneau routier|Verkehrsschild|señal de tráfico|segnale stradale|placa de trânsito
objects|seat|كرسي عربية|مقعد سيارة|siège|Autositz|asiento|sedile|assento
technology|windscreen wiper|مسّاحات العربية|ماسحة زجاج|essuie-glace|Scheibenwischer|limpiaparabrisas|tergicristallo|limpador de para-brisa
actions|overtake|عدّي عربية|تجاوز سيارة|dépasser|überholen|adelantar|sorpassare|ultrapassar
places|petrol station|بنزينة|محطة وقود|station-service|Tankstelle|gasolinera|distributore|posto de combustível
objects|fuel pump|طلمبة بنزين|مضخة وقود|pompe à essence|Zapfsäule|surtidor|pompa di benzina|bomba de combustível
technology|tire|كاوتش|إطار سيارة|pneu|Reifen|neumático|pneumatico|pneu
objects|spare wheel|استبن|عجلة احتياطية|roue de secours|Ersatzrad|rueda de repuesto|ruota di scorta|estepe
actions|refuel|فوّل بنزين|تزوّد بالوقود|faire le plein|tanken|repostar|fare rifornimento|abastecer
work|repair bill|فاتورة تصليح|فاتورة إصلاح|facture de réparation|Reparaturrechnung|factura de reparación|fattura di riparazione|conta do conserto
places|rest stop|استراحة طريق|استراحة طريق|aire de repos|Rastplatz|área de descanso|area di sosta|área de descanso
objects|picnic blanket|مفرش للفسحة|مفرش نزهة|couverture de pique-nique|Picknickdecke|manta de pícnic|coperta da picnic|manta de piquenique
food|packed lunch|أكل للرحلة|وجبة للرحلة|panier-repas|Lunchpaket|comida para llevar|pranzo al sacco|lanche de viagem
actions|stretch|اتمطى|تمدد|s'étirer|sich strecken|estirarse|stiracchiarsi|se espreguiçar
concepts|detour|طريق بديل|طريق التفافي|détour|Umleitung|desvío|deviazione|desvio
feelings|wanderlust|حب السفر|شغف بالسفر|envie de voyager|Fernweh|ganas de viajar|voglia di viaggiare|vontade de viajar`},
        {name: 'City transport', entries: `places|bus depot|جراج الأتوبيسات|مستودع حافلات|dépôt de bus|Busdepot|cochera de autobuses|deposito degli autobus|garagem de ônibus
objects|handrail|مسكة الأتوبيس|درابزين|barre d'appui|Haltestange|pasamanos|corrimano|corrimão
objects|travel card|كارت مواصلات|بطاقة تنقل|carte de transport|Fahrkarte|abono de transporte|abbonamento|cartão de transporte
people|commuter|راكب المواصلات|مسافر للعمل|navetteur|Pendler|viajero habitual|pendolare|passageiro diário
technology|ticket machine|ماكينة تذاكر|آلة بيع التذاكر|distributeur de billets|Fahrkartenautomat|máquina de billetes|biglietteria automatica|máquina de bilhetes
actions|validate|فعّل التذكرة|اعتمد التذكرة|valider|entwerten|validar|convalidare|validar
places|underpass|نفق مشاة|نفق مشاة|passage souterrain|Unterführung|paso subterráneo|sottopassaggio|passagem subterrânea
places|pedestrian crossing|ممر مشاة|معبر مشاة|passage piéton|Zebrastreifen|paso de peatones|attraversamento pedonale|faixa de pedestres
technology|traffic light|إشارة مرور|إشارة مرور|feu tricolore|Ampel|semáforo|semaforo|semáforo
people|cyclist|راكب عجلة|راكب دراجة|cycliste|Radfahrer|ciclista|ciclista|ciclista
objects|bicycle bell|جرس عجلة|جرس دراجة|sonnette de vélo|Fahrradklingel|timbre de bicicleta|campanello|campainha de bicicleta
actions|pedal|بدّل|اضغط على الدواسات|pédaler|in die Pedale treten|pedalear|pedalare|pedalar
places|taxi rank|موقف تاكسي|موقف سيارات أجرة|station de taxis|Taxistand|parada de taxis|posteggio taxi|ponto de táxi
technology|taximeter|عداد تاكسي|عداد أجرة|taximètre|Taxameter|taxímetro|tassametro|taxímetro
work|fare|أجرة|أجرة|tarif|Fahrpreis|tarifa|tariffa|tarifa
time|rush hour|ساعة الزحمة|ساعة الذروة|heure de pointe|Stoßzeit|hora punta|ora di punta|hora de pico
actions|queue|اقف في الطابور|قف في الطابور|faire la queue|anstehen|hacer cola|fare la fila|entrar na fila
concepts|accessibility|سهولة الوصول|إمكانية الوصول|accessibilité|Barrierefreiheit|accesibilidad|accessibilità|acessibilidade`},
        {name: 'Hotel', entries: `places|reception desk|ريسيبشن|مكتب استقبال|réception|Rezeption|recepción|reception|recepção
people|concierge|كونسيرج|مسؤول خدمات|concierge|Concierge|conserje|portiere|concierge
objects|room key|مفتاح أوضة|مفتاح غرفة|clé de chambre|Zimmerschlüssel|llave de habitación|chiave della camera|chave do quarto
work|reservation|حجز|حجز|réservation|Reservierung|reserva|prenotazione|reserva
actions|check in|اعمل تشيك إن|سجّل الوصول|s'enregistrer|einchecken|registrarse|fare il check-in|fazer o check-in
objects|suitcase tag|تيكت الشنطة|بطاقة حقيبة|étiquette de valise|Kofferanhänger|etiqueta de maleta|etichetta del bagaglio|etiqueta de mala
objects|bathrobe|برنس|رداء حمام|peignoir|Bademantel|albornoz|accappatoio|roupão
objects|slippers|شبشب|خف منزلي|chaussons|Hausschuhe|zapatillas|pantofole|pantufas
objects|pillowcase|كيس مخدة|غطاء وسادة|taie d'oreiller|Kissenbezug|funda de almohada|federa|fronha
objects|room safe|خزنة الأوضة|خزنة غرفة|coffre-fort|Zimmersafe|caja fuerte|cassaforte|cofre
people|housekeeper|عاملة نضافة|عاملة تنظيف|femme de chambre|Zimmermädchen|camarera de piso|cameriera ai piani|camareira
actions|unpack|فضّي الشنطة|أفرغ الحقيبة|défaire sa valise|auspacken|deshacer la maleta|disfare la valigia|desfazer a mala
food|breakfast buffet|بوفيه فطار|بوفيه إفطار|buffet du petit déjeuner|Frühstücksbuffet|bufé de desayuno|buffet della colazione|bufê de café da manhã
work|room service|خدمة الأوض|خدمة الغرف|service en chambre|Zimmerservice|servicio de habitaciones|servizio in camera|serviço de quarto
objects|do not disturb sign|يافطة ممنوع الإزعاج|لافتة عدم الإزعاج|pancarte ne pas déranger|Bitte-nicht-stören-Schild|cartel de no molestar|cartello non disturbare|placa de não perturbe
time|checkout time|ميعاد تسليم الأوضة|موعد المغادرة|heure de départ|Abreisezeit|hora de salida|orario di partenza|horário de saída
feelings|hospitality|ضيافة|حسن الضيافة|hospitalité|Gastfreundschaft|hospitalidad|ospitalità|hospitalidade
concepts|comfort|راحة|راحة|confort|Komfort|comodidad|comodità|conforto`},
        {name: 'Exploring a city', entries: `places|alleyway|حارة|زقاق|ruelle|Gasse|callejón|vicolo|beco
places|courtyard|حوش|فناء|cour|Innenhof|patio|cortile|pátio
places|arcade|بواكي|رواق|arcade|Arkade|soportal|portico|arcada
objects|street map|خريطة شوارع|خريطة شوارع|plan de ville|Stadtplan|plano urbano|pianta della città|mapa da cidade
people|tour guide|مرشد سياحي|مرشد سياحي|guide touristique|Reiseführer|guía turístico|guida turistica|guia turístico
actions|stroll|اتمشى|تنزّه|flâner|schlendern|pasear|passeggiare|passear
places|town hall|مبنى الحي|مبنى البلدية|hôtel de ville|Rathaus|ayuntamiento|municipio|prefeitura
places|clock tower|برج ساعة|برج ساعة|beffroi|Uhrturm|torre del reloj|torre dell'orologio|torre do relógio
objects|statue pedestal|قاعدة تمثال|قاعدة تمثال|socle|Statuensockel|pedestal|piedistallo|pedestal
people|street performer|فنان شارع|فنان شارع|artiste de rue|Straßenkünstler|artista callejero|artista di strada|artista de rua
objects|souvenir magnet|مغناطيس التلاجة|مغناطيس تذكاري|aimant souvenir|Souvenirmagnet|imán de recuerdo|calamita ricordo|ímã de lembrança
actions|bargain|فاصل|ساوم|marchander|handeln|regatear|contrattare|pechinchar
places|viewpoint|مطلّ على المدينة|نقطة إطلالة|belvédère|Aussichtspunkt|mirador|belvedere|mirante
objects|binoculars|منظار|منظار|jumelles|Fernglas|prismáticos|binocolo|binóculos
nature|skyline|شكل المدينة من بعيد|خط أفق المدينة|silhouette urbaine|Skyline|perfil urbano|profilo urbano|perfil da cidade
concepts|landmark|مَعلم مشهور|معلم بارز|monument emblématique|Wahrzeichen|lugar emblemático|punto di riferimento|ponto de referência
feelings|fascination|انبهار|افتتان|fascination|Faszination|fascinación|fascino|fascínio
time|opening hours|مواعيد الفتح|ساعات العمل|heures d'ouverture|Öffnungszeiten|horario de apertura|orari di apertura|horário de funcionamento`},
    ]},
    {name: 'Home', scenes: [
        {name: 'Kitchen', entries: `objects|colander|مصفاة|مصفاة|passoire|Sieb|colador|scolapasta|escorredor
objects|chopping board|لوح تقطيع|لوح تقطيع|planche à découper|Schneidebrett|tabla de cortar|tagliere|tábua de cortar
objects|ladle|كبشة|مغرفة|louche|Schöpfkelle|cucharón|mestolo|concha de sopa
objects|whisk|مضرب بيض|مخفقة|fouet|Schneebesen|batidor|frusta|batedor
objects|rolling pin|نشابة|شوبك|rouleau à pâtisserie|Nudelholz|rodillo|mattarello|rolo de massa
actions|knead|اعجن|اعجن|pétrir|kneten|amasar|impastare|amassar
technology|dishwasher|غسالة مواعين|غسالة أطباق|lave-vaisselle|Geschirrspüler|lavavajillas|lavastoviglie|lava-louças
technology|extractor fan|شفاط|شفاط مطبخ|hotte aspirante|Dunstabzugshaube|campana extractora|cappa aspirante|exaustor
technology|freezer|فريزر|مجمّد|congélateur|Gefrierschrank|congelador|congelatore|congelador
objects|oven glove|جوانتي فرن|قفاز فرن|gant de cuisine|Ofenhandschuh|manopla de horno|guanto da forno|luva de forno
actions|defrost|فك التلج|أذب الجليد|décongeler|auftauen|descongelar|scongelare|descongelar
concepts|hygiene|نضافة|نظافة|hygiène|Hygiene|higiene|igiene|higiene
objects|storage jar|برطمان|مرطبان|bocal|Vorratsglas|tarro|barattolo|pote
objects|cling film|بلاستيك تغليف|غلاف حفظ الطعام|film alimentaire|Frischhaltefolie|film transparente|pellicola|filme plástico
objects|kitchen scales|ميزان مطبخ|ميزان مطبخ|balance de cuisine|Küchenwaage|báscula de cocina|bilancia da cucina|balança de cozinha
actions|measure|قيس|قِس|mesurer|messen|medir|misurare|medir
food|leftovers|بواقي أكل|بقايا طعام|restes|Essensreste|sobras|avanzi|sobras
actions|reheat|سخّن تاني|أعد التسخين|réchauffer|aufwärmen|recalentar|riscaldare|reaquecer`},
        {name: 'Dining room', entries: `objects|tablecloth|مفرش سفرة|مفرش مائدة|nappe|Tischdecke|mantel|tovaglia|toalha de mesa
objects|napkin|فوطة سفرة|منديل مائدة|serviette de table|Serviette|servilleta|tovagliolo|guardanapo
objects|placemat|مفرش تحت الطبق|مفرش طبق|set de table|Platzset|salvamanteles|tovaglietta|jogo americano
objects|cutlery|معالق وشوك وسكاكين|أدوات مائدة|couverts|Besteck|cubiertos|posate|talheres
objects|serving tray|صينية التقديم|صينية تقديم|plateau de service|Serviertablett|bandeja|vassoio|bandeja
actions|set the table|حضّر السفرة|جهّز المائدة|mettre la table|den Tisch decken|poner la mesa|apparecchiare|pôr a mesa
objects|water jug|شفشق ميّه|إبريق ماء|carafe|Wasserkrug|jarra de agua|brocca|jarra de água
objects|soup bowl|سلطانية شوربة|وعاء حساء|bol à soupe|Suppenschale|cuenco de sopa|scodella|tigela de sopa
objects|bread basket|سبت عيش|سلة خبز|corbeille à pain|Brotkorb|panera|cestino del pane|cesta de pão
food|side dish|طبق جانبي|طبق جانبي|accompagnement|Beilage|guarnición|contorno|acompanhamento
food|main course|طبق رئيسي|طبق رئيسي|plat principal|Hauptgericht|plato principal|piatto principale|prato principal
food|dessert|تحلية|حلوى|dessert|Nachspeise|postre|dolce|sobremesa
people|hostess|صاحبة العزومة|مضيفة|hôtesse|Gastgeberin|anfitriona|padrona di casa|anfitriã
actions|serve|قدّم الأكل|قدّم الطعام|servir|servieren|servir|servire|servir
actions|pass the salt|ناول الملح|ناول الملح|passer le sel|das Salz reichen|pasar la sal|passare il sale|passar o sal
feelings|appetite|نفس مفتوحة|شهية|appétit|Appetit|apetito|appetito|apetite
concepts|table manners|آداب الأكل|آداب المائدة|bonnes manières|Tischmanieren|modales|buone maniere|boas maneiras
actions|clear the table|لمّ السفرة|ارفع الأطباق|débarrasser|den Tisch abräumen|recoger la mesa|sparecchiare|tirar a mesa`},
        {name: 'Bedroom', entries: `objects|duvet|لحاف|لحاف|couette|Bettdecke|edredón|piumone|edredom
objects|mattress|مرتبة|مرتبة|matelas|Matratze|colchón|materasso|colchão
objects|bedsheet|ملاية|ملاءة|drap|Bettlaken|sábana|lenzuolo|lençol
objects|bedside table|كومودينو|طاولة بجانب السرير|table de chevet|Nachttisch|mesita de noche|comodino|mesa de cabeceira
technology|alarm clock|منبه|منبه|réveil|Wecker|despertador|sveglia|despertador
actions|snooze|غفوة|اغفُ|somnoler|dösen|dormitar|sonnecchiare|cochilar
objects|wardrobe|دولاب هدوم|خزانة ملابس|armoire|Kleiderschrank|armario|armadio|guarda-roupa
objects|clothes hanger|شماعة|علّاقة ملابس|cintre|Kleiderbügel|percha|gruccia|cabide
objects|drawer|درج|درج|tiroir|Schublade|cajón|cassetto|gaveta
objects|dressing gown|روب|رداء منزلي|robe de chambre|Morgenmantel|bata|vestaglia|robe
actions|fold|اطوي|اطوِ|plier|falten|doblar|piegare|dobrar
concepts|tidiness|ترتيب|ترتيب|ordre|Ordnung|orden|ordine|organização
objects|blackout curtain|ستارة بتحجب النور|ستارة معتمة|rideau occultant|Verdunkelungsvorhang|cortina opaca|tenda oscurante|cortina blackout
objects|eye mask|ماسك نوم|قناع نوم|masque de sommeil|Schlafmaske|antifaz|mascherina da notte|máscara de dormir
objects|earplugs|سدادات ودن|سدادات أذن|bouchons d'oreilles|Ohrstöpsel|tapones de oídos|tappi per le orecchie|protetores auriculares
time|bedtime|ميعاد النوم|وقت النوم|heure du coucher|Schlafenszeit|hora de acostarse|ora di dormire|hora de dormir
feelings|drowsiness|نعسان|نعاس|somnolence|Schläfrigkeit|somnolencia|sonnolenza|sonolência
concepts|restful sleep|نوم هادي|نوم مريح|sommeil réparateur|erholsamer Schlaf|sueño reparador|sonno ristoratore|sono reparador`},
        {name: 'Bathroom and laundry', entries: `objects|washbasin|حوض الحمام|مغسلة|lavabo|Waschbecken|lavabo|lavandino|pia
objects|tap|حنفية|صنبور|robinet|Wasserhahn|grifo|rubinetto|torneira
objects|bath mat|دواسة حمام|بساط حمام|tapis de bain|Badematte|alfombrilla de baño|tappetino da bagno|tapete de banho
objects|toothpaste|معجون سنان|معجون أسنان|dentifrice|Zahnpasta|pasta de dientes|dentifricio|pasta de dente
objects|dental floss|خيط سنان|خيط أسنان|fil dentaire|Zahnseide|hilo dental|filo interdentale|fio dental
actions|rinse|اشطف|اشطف|rincer|abspülen|enjuagar|risciacquare|enxaguar
objects|laundry basket|سبت غسيل|سلة غسيل|panier à linge|Wäschekorb|cesto de ropa|cesto della biancheria|cesto de roupa
objects|soap|صابونة|صابون|savon|Seife|jabón|sapone|sabão
objects|detergent|مسحوق غسيل|منظف غسيل|lessive|Waschmittel|detergente|detersivo|detergente
technology|washing machine|غسالة|غسالة|lave-linge|Waschmaschine|lavadora|lavatrice|máquina de lavar
actions|soak|انقع|انقع|faire tremper|einweichen|remojar|mettere a bagno|deixar de molho
objects|stain|بقعة|بقعة|tache|Fleck|mancha|macchia|mancha
objects|clothes peg|مشبك غسيل|مشبك غسيل|pince à linge|Wäscheklammer|pinza de ropa|molletta|prendedor de roupa
objects|clothesline|حبل غسيل|حبل غسيل|corde à linge|Wäscheleine|tendedero|filo per stendere|varal
technology|iron|مكواة|مكواة|fer à repasser|Bügeleisen|plancha|ferro da stiro|ferro de passar
objects|ironing board|ترابيزة المكواة|لوح كي|table à repasser|Bügelbrett|tabla de planchar|asse da stiro|tábua de passar
actions|wring|اعصر|اعصر|essorer|auswringen|escurrir|strizzare|torcer
concepts|freshness|ريحة نضيفة|رائحة منعشة|fraîcheur du linge|Wäschefrische|frescura de la ropa|freschezza del bucato|frescor da roupa`},
        {name: 'Living room', entries: `objects|sofa cushion|خدادية|وسادة أريكة|coussin de canapé|Sofakissen|cojín|cuscino del divano|almofada do sofá
objects|coffee table|ترابيزة صالون|طاولة قهوة|table basse|Couchtisch|mesa de centro|tavolino|mesa de centro
objects|bookshelf|رف كتب|رف كتب|étagère à livres|Bücherregal|estantería|libreria|estante
objects|rug|سجادة صغيرة|بساط|tapis|Teppich|alfombra|tappeto|tapete
objects|lampshade|غطا الأباجورة|غطاء مصباح|abat-jour|Lampenschirm|pantalla de lámpara|paralume|cúpula de abajur
technology|remote control|ريموت|جهاز تحكم عن بعد|télécommande|Fernbedienung|mando a distancia|telecomando|controle remoto
objects|vase|فازة|مزهرية|vase|Vase|jarrón|vaso da fiori|vaso de flores
objects|coaster|قاعدة كوباية|قاعدة كوب|dessous de verre|Untersetzer|posavasos|sottobicchiere|porta-copos
objects|photo album|ألبوم صور|ألبوم صور|album photo|Fotoalbum|álbum de fotos|album fotografico|álbum de fotos
objects|keepsake|حاجة للذكرى|تذكار عزيز|souvenir précieux|Andenken|recuerdo querido|ricordo prezioso|lembrança querida
actions|rearrange|رتّب من جديد|أعد الترتيب|réorganiser|umstellen|reorganizar|riorganizzare|reorganizar
feelings|coziness|دفا البيت|دفء البيت|ambiance douillette|Gemütlichkeit|calidez del hogar|intimità domestica|aconchego
objects|armchair|فوتيه|كرسي بذراعين|fauteuil|Sessel|sillón|poltrona|poltrona
objects|footstool|مسند للرجلين|مسند قدمين|repose-pieds|Fußhocker|reposapiés|poggiapiedi|apoio para os pés
objects|knitting needles|إبر تريكو|إبر حياكة|aiguilles à tricoter|Stricknadeln|agujas de tejer|ferri da maglia|agulhas de tricô
objects|yarn|خيط صوف|خيط صوف|laine à tricoter|Strickgarn|lana para tejer|filato|fio de lã
actions|knit|اشتغل تريكو|حياكة|tricoter|stricken|tejer|lavorare a maglia|tricotar
time|quiet evening|سهرة هادية|أمسية هادئة|soirée tranquille|ruhiger Abend|tarde tranquila|serata tranquilla|noite tranquila`},
        {name: 'Repairs', entries: `people|plumber|سباك|سباك|plombier|Klempner|fontanero|idraulico|encanador
objects|wrench|مفتاح ربط|مفتاح ربط|clé à molette|Schraubenschlüssel|llave inglesa|chiave inglese|chave inglesa
objects|washer ring|وردة كاوتش|حلقة مطاطية|joint|Dichtungsring|arandela|guarnizione|arruela
objects|leak|تسريب|تسرب|fuite|Leck|fuga|perdita|vazamento
actions|tighten|اربط|أحكم الربط|serrer|festziehen|apretar|stringere|apertar
concepts|water pressure|ضغط الميّه|ضغط الماء|pression de l'eau|Wasserdruck|presión del agua|pressione dell'acqua|pressão da água
people|electrician|كهربائي|كهربائي|électricien|Elektriker|electricista|elettricista|eletricista
technology|fuse|فيوز|مصهر|fusible|Sicherung|fusible|fusibile|fusível
technology|socket|بريزة كهربا|مقبس|prise|Steckdose|enchufe|presa|tomada
objects|insulating tape|شريط لحام|شريط عازل|ruban isolant|Isolierband|cinta aislante|nastro isolante|fita isolante
technology|circuit breaker|قاطع كهربا|قاطع كهربائي|disjoncteur|Schutzschalter|disyuntor|interruttore automatico|disjuntor
actions|disconnect|افصل الكهربا|افصل|débrancher|trennen|desconectar|scollegare|desconectar
objects|screwdriver|مفك|مفك براغي|tournevis|Schraubendreher|destornillador|cacciavite|chave de fenda
objects|wall plug|فيشر|وتد تثبيت|cheville|Dübel|taco de pared|tassello|bucha
objects|spirit level|ميزان ميّه|ميزان ماء|niveau à bulle|Wasserwaage|nivel de burbuja|livella|nível de bolha
actions|drill|اخرم|اثقب|percer|bohren|taladrar|trapanare|furar
work|maintenance|صيانة|صيانة|entretien|Wartung|mantenimiento|manutenzione|manutenção
concepts|durability|متانة|متانة|durabilité|Haltbarkeit|durabilidad|durata|durabilidade`},
    ]},
    {name: 'Food', scenes: [
        {name: 'Vegetable stall', entries: `food|aubergine|بتنجان|باذنجان|aubergine|Aubergine|berenjena|melanzana|berinjela
food|courgette|كوسة|كوسا|courgette|Zucchini|calabacín|zucchina|abobrinha
food|cauliflower|قرنبيط|قرنبيط|chou-fleur|Blumenkohl|coliflor|cavolfiore|couve-flor
food|cabbage|كرنب|ملفوف|chou|Kohl|repollo|cavolo|repolho
food|leek|كرات|كراث|poireau|Lauch|puerro|porro|alho-poró
food|celery|كرفس|كرفس|céleri|Sellerie|apio|sedano|aipo
food|beetroot|بنجر|شمندر|betterave|Rote Bete|remolacha|barbabietola|beterraba
food|radish|فجل|فجل|radis|Radieschen|rábano|ravanello|rabanete
food|turnip|لفت|لفت|navet|Rübe|nabo|rapa|nabo
food|spinach|سبانخ|سبانخ|épinards|Spinat|espinaca|spinaci|espinafre
food|okra|بامية|بامية|gombo|Okra|okra|gombo|quiabo
food|artichoke|خرشوف|خرشوف|artichaut|Artischocke|alcachofa|carciofo|alcachofra
food|parsley|بقدونس|بقدونس|persil|Petersilie|perejil|prezzemolo|salsa
food|coriander|كزبرة|كزبرة|coriandre|Koriander|cilantro|coriandolo|coentro
food|dill|شبت|شبت|aneth|Dill|eneldo|aneto|endro
food|spring onion|بصل أخضر|بصل أخضر|oignon nouveau|Frühlingszwiebel|cebolleta|cipollotto|cebolinha
actions|weigh|اوزن|زِن|peser|wiegen|pesar|pesare|pesar
work|wholesale|جملة|بيع بالجملة|vente en gros|Großhandel|venta al por mayor|vendita all'ingrosso|atacado`},
        {name: 'Fruit orchard', entries: `food|apricot|مشمش|مشمش|abricot|Aprikose|albaricoque|albicocca|damasco
food|plum|برقوق|برقوق|prune|Pflaume|ciruela|prugna|ameixa
food|fig|تين|تين|figue|Feige|higo|fico|figo
food|pomegranate|رمان|رمان|grenade|Granatapfel|granada|melagrana|romã
food|guava|جوافة|جوافة|goyave|Guave|guayaba|guava|goiaba
food|pear|كمثرى|كمثرى|poire|Birne|pera|pera|pera
food|grapefruit|جريب فروت|جريب فروت|pamplemousse|Grapefruit|pomelo|pompelmo|toranja
food|mandarin|يوسفي|يوسفي|mandarine|Mandarine|mandarina|mandarino|tangerina
food|persimmon|كاكا|كاكي|kaki|Kaki|caqui|cachi|caqui
food|mulberry|توت|توت|mûre du mûrier|Maulbeere|mora de morera|mora di gelso|amora
food|raspberry|توت أحمر|توت العليق|framboise|Himbeere|frambuesa|lampone|framboesa
food|blackberry|توت أسود|عليق أسود|mûre|Brombeere|zarzamora|mora|amora-preta
nature|blossom|زهر|زهر|fleur d'arbre|Baumblüte|flor de árbol|fiore d'albero|flor de árvore
objects|fruit crate|قفص فاكهة|صندوق فاكهة|cagette|Obstkiste|cajón de fruta|cassetta di frutta|caixote de frutas
actions|pick fruit|اقطف|اقطف|cueillir|pflücken|cosechar fruta|cogliere|colher
concepts|ripeness|فاكهة مستوية|نضج|maturité du fruit|Fruchtreife|madurez del fruto|maturazione|amadurecimento
food|fruit pulp|لب الفاكهة|لب الفاكهة|pulpe|Fruchtfleisch|pulpa|polpa|polpa
actions|preserve fruit|اعمل مربى|احفظ الفاكهة|mettre en conserve|einmachen|conservar fruta|conservare la frutta|conservar frutas`},
        {name: 'Bakery', entries: `food|yeast|خميرة|خميرة|levure|Hefe|levadura|lievito|fermento
food|sourdough|عجينة بخميرة طبيعية|عجين مخمر طبيعيا|levain|Sauerteig|masa madre|lievito madre|massa de fermentação natural
food|semolina|سميد|سميد|semoule|Grieß|sémola|semola|sêmola
food|bran|ردة|نخالة|son|Kleie|salvado|crusca|farelo
food|baguette|عيش باجيت|خبز باغيت|baguette|Baguette|barra de pan|baguette|baguete
food|pita|عيش بلدي|خبز عربي|pain pita|Fladenbrot|pan de pita|pane pita|pão pita
food|croissant|كرواسون|كرواسون|croissant|Croissant|cruasán|cornetto|croissant
food|pretzel|بريتزل|بريتزل|bretzel|Brezel|pretzel|brezel|pretzel
food|bun|عيش مدور|خبزة صغيرة|petit pain|Brötchen|panecillo|panino|pãozinho
food|bread crust|قشرة العيش|قشرة الخبز|croûte|Brotkruste|corteza|crosta|casca do pão
food|bread crumb|لب العيش|لب الخبز|mie|Brotkrume|miga|mollica|miolo do pão
actions|rise|تخمير العجينة|تخمّر العجين|lever|aufgehen|leudar|lievitare|fermentar
objects|baking tin|صينية فرن|قالب خبز|moule à pain|Backform|molde de pan|stampo da pane|forma de pão
objects|baking paper|ورق زبدة|ورق خبز|papier cuisson|Backpapier|papel de horno|carta da forno|papel manteiga
people|pastry chef|حلواني|صانع حلويات|pâtissier|Konditor|pastelero|pasticciere|confeiteiro
actions|sift|انخل|انخل|tamiser|sieben|tamizar|setacciare|peneirar
food|icing|جليز سكر|طبقة سكر|glaçage|Zuckerguss|glaseado|glassa|glacê
food|sprinkles|فارماسيل|حبيبات تزيين|vermicelles sucrés|Zuckerstreusel|fideos de azúcar|codette di zucchero|granulado`},
        {name: 'Cafe', entries: `food|espresso|إسبرسو|إسبرسو|expresso|Espresso|expreso|espresso|expresso
food|cappuccino|كابتشينو|كابتشينو|cappuccino|Cappuccino|capuchino|cappuccino|cappuccino
food|hot chocolate|هوت شوكليت|شوكولاتة ساخنة|chocolat chaud|heiße Schokolade|chocolate caliente|cioccolata calda|chocolate quente
food|herbal tea|شاي أعشاب|شاي أعشاب|tisane|Kräutertee|infusión|tisana|chá de ervas
food|hibiscus tea|كركديه|كركديه|infusion d'hibiscus|Hibiskustee|té de hibisco|tè all'ibisco|chá de hibisco
food|aniseed|ينسون|يانسون|anis|Anis|anís|anice|erva-doce
objects|coffee grinder|مطحنة بن|مطحنة قهوة|moulin à café|Kaffeemühle|molinillo de café|macinacaffè|moedor de café
objects|coffee filter|فلتر قهوة|مرشح قهوة|filtre à café|Kaffeefilter|filtro de café|filtro da caffè|filtro de café
objects|teapot|براد شاي|إبريق شاي|théière|Teekanne|tetera|teiera|bule
objects|saucer|طبق فنجان|صحن فنجان|soucoupe|Untertasse|platillo|piattino|pires
people|barista|باريستا|صانع قهوة|barista|Barista|barista|barista|barista
actions|sip|خد رشفة|ارتشف|siroter|nippen|sorber|sorseggiare|bebericar
food|caffeine|كافيين|كافيين|caféine|Koffein|cafeína|caffeina|cafeína
food|froth|رغوة لبن|رغوة حليب|mousse de lait|Milchschaum|espuma de leche|schiuma di latte|espuma de leite
food|sugar cube|مكعب سكر|مكعب سكر|morceau de sucre|Zuckerwürfel|terrón de azúcar|zolletta di zucchero|cubo de açúcar
objects|takeaway cup|كوباية تيك أواي|كوب سفري|gobelet à emporter|Mitnahmebecher|vaso para llevar|bicchiere da asporto|copo para viagem
work|tip|بقشيش|إكرامية|pourboire|Trinkgeld|propina|mancia|gorjeta
time|coffee break|بريك قهوة|استراحة قهوة|pause-café|Kaffeepause|pausa para el café|pausa caffè|pausa para o café`},
        {name: 'Spice cupboard', entries: `food|cumin|كمون|كمون|cumin|Kreuzkümmel|comino|cumino|cominho
food|turmeric|كركم|كركم|curcuma|Kurkuma|cúrcuma|curcuma|cúrcuma
food|cardamom|حبهان|هال|cardamome|Kardamom|cardamomo|cardamomo|cardamomo
food|clove|قرنفل|قرنفل|clou de girofle|Gewürznelke|clavo|chiodo di garofano|cravo
food|nutmeg|جوزة الطيب|جوزة الطيب|noix de muscade|Muskatnuss|nuez moscada|noce moscata|noz-moscada
food|saffron|زعفران|زعفران|safran|Safran|azafrán|zafferano|açafrão
food|ginger|جنزبيل|زنجبيل|gingembre|Ingwer|jengibre|zenzero|gengibre
food|paprika|بابريكا|فلفل حلو مطحون|paprika|Paprikapulver|pimentón|paprica|páprica
food|chilli|شطة|فلفل حار|piment|Chili|chile|peperoncino|pimenta-malagueta
food|black pepper|فلفل أسود|فلفل أسود|poivre noir|schwarzer Pfeffer|pimienta negra|pepe nero|pimenta-do-reino
food|bay leaf|ورق لورا|ورق غار|feuille de laurier|Lorbeerblatt|hoja de laurel|foglia d'alloro|folha de louro
food|thyme|زعتر|زعتر|thym|Thymian|tomillo|timo|tomilho
food|rosemary|روزماري|إكليل الجبل|romarin|Rosmarin|romero|rosmarino|alecrim
food|basil|ريحان|ريحان|basilic|Basilikum|albahaca|basilico|manjericão
food|oregano|أوريجانو|مردقوش بري|origan|Oregano|orégano|origano|orégano
objects|pestle|إيد الهون|مدقة|pilon|Stößel|mano de mortero|pestello|pilão
objects|mortar bowl|هون|هاون|mortier|Mörser|mortero|mortaio|almofariz
actions|season|تبّل|تبّل|assaisonner|würzen|sazonar|condire|temperar`},
        {name: 'Restaurant', entries: `people|waiter|جرسون|نادل|serveur|Kellner|camarero|cameriere|garçom
people|chef|شيف|طاهٍ|chef cuisinier|Koch|cocinero|cuoco|cozinheiro
objects|menu|منيو|قائمة طعام|menu|Speisekarte|carta|menù|cardápio
work|order|طلب أكل|طلب طعام|commande|Bestellung|pedido|ordinazione|pedido
food|starter|مقبلات|مقبلات|entrée|Vorspeise|entrante|antipasto|entrada
food|soup of the day|شوربة اليوم|حساء اليوم|soupe du jour|Tagessuppe|sopa del día|zuppa del giorno|sopa do dia
food|lentils|عدس|عدس|lentilles|Linsen|lentejas|lenticchie|lentilhas
food|chickpeas|حمص|حمص|pois chiches|Kichererbsen|garbanzos|ceci|grão-de-bico
food|broad beans|فول|فول|fèves|Saubohnen|habas|fave|favas
food|falafel|طعمية|فلافل|falafels|Falafel|falafel|falafel|falafel
food|tahini|طحينة|طحينة|tahini|Tahini|tahini|tahina|tahine
food|stuffed vine leaves|ورق عنب|ورق عنب محشو|feuilles de vigne farcies|gefüllte Weinblätter|hojas de parra rellenas|foglie di vite ripiene|folhas de uva recheadas
food|rice pudding|رز بلبن|أرز بالحليب|riz au lait|Milchreis|arroz con leche|riso al latte|arroz-doce
food|custard|كاسترد|كاسترد|crème anglaise|Vanillecreme|natillas|crema pasticcera|creme de baunilha
food|sorbet|سوربيه|مثلجات فاكهة|sorbet|Sorbet|sorbete|sorbetto|sorvete de fruta
work|bill|حساب|فاتورة مطعم|addition|Rechnung|cuenta|conto|conta
actions|split the bill|اتقاسموا الحساب|تقاسم الحساب|partager l'addition|die Rechnung teilen|dividir la cuenta|dividere il conto|dividir a conta
feelings|fullness|شبع|شبع|satiété|Sättigung|saciedad|sazietà|saciedade`},
    ]},
    {name: 'Community', scenes: [
        {name: 'Classroom', entries: `people|headteacher|ناظر|مدير مدرسة|directeur d'école|Schulleiter|director escolar|preside|diretor escolar
people|classmate|زميل فصل|زميل صف|camarade de classe|Klassenkamerad|compañero de clase|compagno di classe|colega de classe
objects|blackboard|سبورة|سبورة|tableau noir|Tafel|pizarra|lavagna|quadro-negro
objects|chalk|طباشير|طباشير|craie|Kreide|tiza|gesso|giz
objects|eraser|أستيكة|ممحاة|gomme|Radiergummi|goma de borrar|gomma|borracha
objects|pencil sharpener|براية|مبراة|taille-crayon|Spitzer|sacapuntas|temperamatite|apontador
work|homework|واجب|واجب منزلي|devoirs|Hausaufgaben|deberes|compiti|lição de casa
work|lesson plan|خطة درس|خطة درس|plan de leçon|Unterrichtsplan|plan de clase|piano della lezione|plano de aula
work|attendance|حضور|حضور|présence|Anwesenheit|asistencia|presenza|presença
time|school term|ترم|فصل دراسي|trimestre scolaire|Schulhalbjahr|trimestre escolar|trimestre scolastico|período letivo
actions|revise|راجع|راجع|réviser|wiederholen|repasar|ripassare|revisar
concepts|explanation|شرح|شرح|explication|Erklärung|explicación|spiegazione|explicação
concepts|spelling|إملاء|تهجئة|orthographe|Rechtschreibung|ortografía|ortografia|ortografia
concepts|pronunciation|نطق|نطق|prononciation|Aussprache|pronunciación|pronuncia|pronúncia
concepts|handwriting|خط الإيد|خط اليد|écriture manuscrite|Handschrift|caligrafía|calligrafia|caligrafia
actions|underline|حط خط|ضع خطًا|souligner|unterstreichen|subrayar|sottolineare|sublinhar
actions|raise a hand|ارفع إيدك|ارفع يدك|lever la main|sich melden|levantar la mano|alzare la mano|levantar a mão
feelings|eagerness|نفسه يتعلم|رغبة في التعلّم|envie d'apprendre|Lerneifer|ansias de aprender|voglia di imparare|vontade de aprender`},
        {name: 'Library', entries: `people|librarian|أمين مكتبة|أمين مكتبة|bibliothécaire|Bibliothekar|bibliotecario|bibliotecario|bibliotecário
objects|library card|كارنيه مكتبة|بطاقة مكتبة|carte de bibliothèque|Bibliotheksausweis|carné de biblioteca|tessera della biblioteca|carteirinha da biblioteca
work|catalog|فهرس|فهرس|catalogue|Katalog|catálogo|catalogo|catálogo
objects|bookmark|بوكمارك|فاصل كتاب|marque-page|Lesezeichen|marcapáginas|segnalibro|marcador de página
objects|book spine|كعب كتاب|كعب كتاب|dos de livre|Buchrücken|lomo|dorso|lombada
actions|borrow|استلف|استعِر|emprunter|ausleihen|tomar prestado|prendere in prestito|pegar emprestado
concepts|fiction|قصص من الخيال|أدب خيالي|fiction|Belletristik|ficción|narrativa|ficção
concepts|biography|حكاية حياة|سيرة حياة|biographie|Biografie|biografía|biografia|biografia
objects|encyclopedia|موسوعة|موسوعة|encyclopédie|Enzyklopädie|enciclopedia|enciclopedia|enciclopédia
objects|dictionary|قاموس|قاموس|dictionnaire|Wörterbuch|diccionario|dizionario|dicionário
concepts|chapter|فصل كتاب|فصل كتاب|chapitre|Kapitel|capítulo|capitolo|capítulo
concepts|quotation|اقتباس|اقتباس|citation|Zitat|cita|citazione|citação
people|author|مؤلف|مؤلف|auteur|Autor|autor|autore|autor
people|illustrator|رسام كتب|رسام توضيحي|illustrateur|Illustrator|ilustrador|illustratore|ilustrador
work|publisher|دار نشر|ناشر|éditeur|Verlag|editorial|editore|editora
actions|recommend|رشّح|رشّح|recommander|empfehlen|recomendar|consigliare|recomendar
time|due date|ميعاد التسليم|موعد إعادة الكتاب|date de retour|Rückgabefrist|fecha de devolución|scadenza|data de devolução
feelings|absorption|مندمج في القراية|استغراق|absorption|Vertiefung|ensimismamiento|immersione nella lettura|imersão na leitura`},
        {name: 'Clinic', entries: `people|receptionist|موظف استقبال|موظف استقبال|réceptionniste|Empfangskraft|recepcionista|addetto all'accoglienza|recepcionista
people|nurse|ممرض|ممرض|infirmier|Krankenpfleger|enfermero|infermiere|enfermeiro
people|dentist|دكتور سنان|طبيب أسنان|dentiste|Zahnarzt|dentista|dentista|dentista
people|pharmacist|صيدلي|صيدلي|pharmacien|Apotheker|farmacéutico|farmacista|farmacêutico
work|appointment|ميعاد كشف|موعد طبي|rendez-vous médical|Arzttermin|cita médica|appuntamento medico|consulta marcada
places|waiting room|صالة انتظار|غرفة انتظار|salle d'attente|Wartezimmer|sala de espera|sala d'attesa|sala de espera
objects|thermometer|ترمومتر|مقياس حرارة|thermomètre|Thermometer|termómetro|termometro|termômetro
objects|stethoscope|سماعة دكتور|سماعة طبيب|stéthoscope|Stethoskop|estetoscopio|stetoscopio|estetoscópio
objects|bandage|رباط شاش|ضمادة|bandage|Verband|venda|benda|atadura
objects|plaster|بلاستر|لاصق جروح|pansement|Pflaster|tirita|cerotto|curativo
work|prescription|روشتة|وصفة طبية|ordonnance|Rezept|receta médica|ricetta medica|receita médica
actions|examine|اكشف|افحص|examiner|untersuchen|examinar|visitare|examinar
concepts|recovery|تعافي|تعافٍ|rétablissement|Genesung|recuperación|guarigione|recuperação
concepts|prevention|وقاية|وقاية|prévention|Vorbeugung|prevención|prevenzione|prevenção
concepts|wellbeing|عافية|عافية|bien-être|Wohlbefinden|bienestar|benessere|bem-estar
actions|rest|ارتاح|استرح|se reposer|sich ausruhen|descansar|riposare|descansar
feelings|reassurance|تطمين|طمأنة|réconfort|Beruhigung|tranquilidad|rassicurazione|tranquilização
time|visiting hours|مواعيد زيارة|ساعات الزيارة|heures de visite|Besuchszeiten|horario de visitas|orario di visita|horário de visitas`},
        {name: 'Post and delivery', entries: `people|postman|بوسطجي|ساعي بريد|facteur|Postbote|cartero|postino|carteiro
people|courier|مندوب توصيل|مندوب توصيل|coursier|Kurier|mensajero|corriere|entregador
places|post office|مكتب بوسطة|مكتب بريد|bureau de poste|Postamt|oficina de correos|ufficio postale|agência dos correios
objects|postbox|صندوق بوسطة|صندوق بريد|boîte aux lettres|Briefkasten|buzón|cassetta postale|caixa de correio
objects|postage stamp|طابع بريد|طابع بريدي|timbre-poste|Briefmarke|sello postal|francobollo|selo postal
objects|postcard|كارت بوستال|بطاقة بريدية|carte postale|Postkarte|postal|cartolina|cartão-postal
objects|parcel|طرد|طرد|colis|Paket|paquete|pacco|encomenda
objects|bubble wrap|بلاستيك فقاقيع|غلاف فقاعي|papier bulle|Luftpolsterfolie|plástico de burbujas|pluriball|plástico-bolha
objects|packing tape|شريط تغليف|شريط تغليف|ruban adhésif d'emballage|Paketband|cinta de embalaje|nastro da imballaggio|fita de embalagem
work|tracking number|رقم تتبع|رقم تتبع|numéro de suivi|Sendungsnummer|número de seguimiento|numero di tracciamento|código de rastreamento
work|postcode|رقم بريدي|رمز بريدي|code postal|Postleitzahl|código postal|codice postale|código postal
actions|deliver|وصّل|سلّم|livrer|zustellen|entregar|consegnare|entregar
concepts|sender|اللي بعت الجواب|مرسل|expéditeur|Absender|remitente|mittente|remetente
concepts|recipient|مستلم|مستلم|destinataire|Empfänger|destinatario|destinatario|destinatário
work|signature|إمضا|توقيع|signature|Unterschrift|firma|firma|assinatura
actions|wrap|غلّف|غلّف|emballer|einpacken|envolver|incartare|embrulhar
time|delivery window|ميعاد التوصيل|موعد التسليم|créneau de livraison|Lieferzeitfenster|franja de entrega|fascia di consegna|janela de entrega
feelings|pleasant surprise|مفاجأة حلوة|مفاجأة سارة|bonne surprise|freudige Überraschung|sorpresa agradable|bella sorpresa|surpresa agradável`},
        {name: 'Emergency services', entries: `people|firefighter|راجل مطافي|رجل إطفاء|pompier|Feuerwehrmann|bombero|pompiere|bombeiro
people|paramedic|مسعف|مسعف|ambulancier|Rettungssanitäter|paramédico|soccorritore|socorrista
people|volunteer|متطوع|متطوع|bénévole|Freiwilliger|voluntario|volontario|voluntário
technology|fire engine|عربية مطافي|سيارة إطفاء|camion de pompiers|Feuerwehrauto|camión de bomberos|autopompa|caminhão de bombeiros
technology|ambulance|عربية إسعاف|سيارة إسعاف|ambulance|Krankenwagen|ambulancia|ambulanza|ambulância
technology|siren|سرينة|صفارة إنذار|sirène|Sirene|sirena|sirena|sirene
objects|fire hose|خرطوم مطافي|خرطوم إطفاء|tuyau d’incendie|Feuerwehrschlauch|manguera contra incendios|manichetta|mangueira de incêndio
objects|fire extinguisher|طفاية حريق|مطفأة حريق|extincteur|Feuerlöscher|extintor|estintore|extintor
objects|stretcher|نقالة|نقالة|brancard|Trage|camilla|barella|maca
objects|first aid kit|شنطة إسعافات أولية|حقيبة إسعافات أولية|trousse de secours|Erste-Hilfe-Kasten|botiquín|cassetta di pronto soccorso|kit de primeiros socorros
actions|evacuate|فضّي المكان|أخلِ المكان|évacuer|evakuieren|evacuar|evacuare|evacuar
concepts|safety drill|تدريب على الطوارئ|تدريب على الإخلاء|exercice de sécurité|Sicherheitsübung|simulacro|esercitazione|simulado de segurança
concepts|team effort|مجهود جماعي|جهد جماعي|effort collectif|Teamarbeit|esfuerzo colectivo|sforzo collettivo|esforço coletivo
actions|assist|ساعد|ساعد|aider|helfen|ayudar|assistere|ajudar
actions|alert|نبّه|نبّه|alerter|alarmieren|alertar|allertare|alertar
feelings|bravery|شجاعة|بسالة|bravoure|Tapferkeit|valentía|valorosità|bravura
feelings|urgency|حالة مستعجلة|حالة عاجلة|urgence|Dringlichkeit|urgencia|urgenza|urgência
concepts|safe return|رجوع بالسلامة|عودة سالمة|retour sain et sauf|sichere Rückkehr|regreso a salvo|ritorno sicuro|retorno em segurança`},
        {name: 'Neighbourhood festival', entries: `places|community center|مركز شباب|مركز مجتمعي|centre communautaire|Gemeindezentrum|centro comunitario|centro civico|centro comunitário
people|organizer|منظم|منظم|organisateur|Organisator|organizador|organizzatore|organizador
people|neighborhood elder|كبير الحتة|وجيه الحي|ancien du quartier|Dorfältester|mayor del barrio|anziano del quartiere|ancião do bairro
objects|bunting|زينة أعلام|أعلام الزينة|guirlande de fanions|Wimpelkette|banderines|festoni|bandeirinhas
objects|paper lantern|فانوس ورق|فانوس ورقي|lanterne en papier|Papierlaterne|farol de papel|lanterna di carta|lanterna de papel
objects|donation box|صندوق تبرعات|صندوق تبرعات|urne de dons|Spendenbox|caja de donaciones|cassetta delle offerte|caixa de doações
work|fundraiser|حملة تبرعات|حملة تبرعات|collecte de fonds|Spendenaktion|recaudación de fondos|raccolta fondi|arrecadação de fundos
work|stall|كشك بيع|كشك بيع|stand|Verkaufsstand|puesto|bancarella|barraca
work|raffle|سحب جوايز|سحب على الجوائز|tombola|Tombola|rifa|lotteria|rifa
actions|decorate|زيّن|زيّن|décorer|schmücken|decorar|decorare|decorar
actions|donate|اتبرع|تبرع|faire un don|spenden|donar|donare|doar
actions|welcome|رحّب|رحّب|accueillir|begrüßen|dar la bienvenida|accogliere|acolher
concepts|belonging|انتماء|انتماء|appartenance|Zugehörigkeit|pertenencia|appartenenza|pertencimento
concepts|generosity|كرم|سخاء|générosité|Großzügigkeit|generosidad|generosità|generosidade
feelings|togetherness|لمة|تآلف|convivialité|Gemeinschaftsgefühl|unión|affiatamento|união
feelings|goodwill|حسن النية|حسن نية|bonne volonté|Wohlwollen|buena voluntad|benevolenza|boa vontade
time|annual celebration|احتفال سنوي|احتفال سنوي|fête annuelle|Jahresfest|fiesta anual|festa annuale|festa anual
concepts|shared tradition|عادة مشتركة|تقليد مشترك|tradition commune|gemeinsame Tradition|tradición compartida|tradizione condivisa|tradição compartilhada`},
    ]},
    {name: 'Arts and crafts', scenes: [
        {name: 'Painting studio', entries: `objects|easel|حامل رسم|حامل رسم|chevalet|Staffelei|caballete|cavalletto|cavalete
objects|canvas|كانفاس|قماش رسم|toile|Leinwand|lienzo|tela da pittura|tela de pintura
objects|palette|باليتة ألوان|لوحة ألوان|palette|Palette|paleta|tavolozza|paleta
objects|paintbrush|فرشة رسم|فرشاة رسم|pinceau|Pinsel|pincel|pennello|pincel
objects|watercolor|ألوان ميّه|ألوان مائية|aquarelle|Wasserfarbe|acuarela|acquerello|aquarela
objects|charcoal stick|قلم فحم|قلم فحم|fusain|Zeichenkohle|carboncillo|carboncino|carvão de desenho
concepts|portrait|بورتريه|صورة شخصية|portrait|Porträt|retrato|ritratto|retrato
concepts|landscape painting|لوحة منظر طبيعي|لوحة منظر طبيعي|peinture de paysage|Landschaftsgemälde|pintura de paisaje|paesaggio dipinto|pintura de paisagem
concepts|still life|طبيعة صامتة|طبيعة صامتة|nature morte|Stillleben|naturaleza muerta|natura morta|natureza-morta
concepts|perspective|منظور|منظور|perspective|Perspektive|perspectiva|prospettiva|perspectiva
concepts|contrast|تباين|تباين|contraste|Kontrast|contraste|contrasto|contraste
actions|blend|اخلط الألوان|امزج|mélanger les couleurs|verblenden|mezclar colores|sfumare|misturar cores
people|model|موديل رسم|نموذج بشري|modèle|Modell|modelo artístico|modello artistico|modelo artístico
people|art critic|ناقد فني|ناقد فني|critique d'art|Kunstkritiker|crítico de arte|critico d'arte|crítico de arte
work|exhibition|معرض فني|معرض فني|exposition|Ausstellung|exposición|mostra|exposição
actions|sketch|ارسم سكتش|رسم أولي|esquisser|skizzieren|bosquejar|abbozzare|esboçar
feelings|inspiration|إلهام|إلهام|inspiration|Inspiration|inspiración|ispirazione|inspiração
concepts|originality|أصالة|أصالة|originalité|Originalität|originalidad|originalità|originalidade`},
        {name: 'Pottery', entries: `nature|clay|طين|طين|argile|Ton|arcilla|argilla|argila
technology|potter's wheel|دولاب الفخار|دولاب الخزاف|tour de potier|Töpferscheibe|torno de alfarero|tornio da vasaio|roda de oleiro
technology|kiln|فرن فخار|فرن خزف|four de potier|Brennofen|horno cerámico|forno da ceramica|forno de cerâmica
objects|glaze|طلاء الفخار|طلاء زجاجي|glaçure|Glasur|esmalte cerámico|smalto ceramico|esmalte cerâmico
objects|pottery shard|حتة فخار مكسورة|شظية فخار|tesson|Tonscherbe|fragmento de cerámica|coccio|caco de cerâmica
people|potter|فخراني|خزاف|potier|Töpfer|alfarero|vasaio|oleiro
actions|mold|شكّل|شكّل|modeler|formen|moldear|modellare|moldar
actions|carve|انحت|انحت|sculpter|schnitzen|tallar|intagliare|entalhar
actions|polish|لمّع|لمّع|polir|polieren|pulir|lucidare|polir
concepts|symmetry|تماثل|تناظر|symétrie|Symmetrie|simetría|simmetria|simetria
concepts|texture|ملمس|ملمس|texture|Textur|textura|consistenza|textura
concepts|fragility|سهل يتكسر|هشاشة|fragilité|Zerbrechlichkeit|fragilidad|fragilità|fragilidade
objects|terracotta|فخار أحمر|فخار أحمر|terre cuite|Terrakotta|terracota|terracotta|terracota
objects|ceramic tile|بلاطة سيراميك|بلاطة خزفية|carreau de céramique|Keramikfliese|baldosa cerámica|piastrella|azulejo
objects|mosaic|فسيفساء|فسيفساء|mosaïque|Mosaik|mosaico|mosaico|mosaico
actions|fire pottery|احرق الفخار|حرق الفخار|cuire la poterie|Keramik brennen|cocer cerámica|cuocere la ceramica|queimar cerâmica
time|drying time|وقت التنشيف|مدة التجفيف|temps de séchage|Trockenzeit|tiempo de secado|tempo di asciugatura|tempo de secagem
feelings|pride in craft|فخور بصنعته|اعتزاز بالحرفة|fierté du métier|Handwerkerstolz|orgullo artesanal|orgoglio artigiano|orgulho artesanal`},
        {name: 'Woodwork and sewing', entries: `people|carpenter|نجار|نجار|menuisier|Tischler|carpintero|falegname|carpinteiro
objects|chisel|إزميل|إزميل|ciseau à bois|Stechbeitel|formón|scalpello|formão
objects|sandpaper|صنفرة|ورق صنفرة|papier de verre|Schleifpapier|papel de lija|carta vetrata|lixa
objects|wood shavings|نشارة|نشارة خشب|copeaux|Holzspäne|virutas|trucioli|aparas de madeira
objects|wood glue|غرا خشب|غراء خشب|colle à bois|Holzleim|cola de madera|colla per legno|cola de madeira
actions|saw|انشر الخشب|انشر الخشب|scier|sägen|serrar|segare|serrar
people|tailor|ترزي|خياط|tailleur|Schneider|sastre|sarto|alfaiate
objects|thimble|كشتبان|كشتبان|dé à coudre|Fingerhut|dedal|ditale|dedal
objects|sewing needle|إبرة خياطة|إبرة خياطة|aiguille à coudre|Nähnadel|aguja de coser|ago da cucito|agulha de costura
objects|spool|بكرة خيط|بكرة خيط|bobine|Garnrolle|carrete|rocchetto|carretel
objects|zip|سوستة|سحاب|fermeture éclair|Reißverschluss|cremallera|cerniera|zíper
actions|stitch|خيّط|خِط|coudre|nähen|coser|cucire|costurar
objects|linen|كتان|كتان|lin|Leinen|lino|lino|linho
objects|velvet|قطيفة|مخمل|velours|Samt|terciopelo|velluto|veludo
objects|silk|حرير|حرير|soie|Seide|seda|seta|seda
work|pattern|باترون|نموذج تفصيل|patron|Schnittmuster|patrón de costura|cartamodello|molde de costura
actions|embroider|طرّز|طرّز|broder|sticken|bordar|ricamare|bordar
concepts|craftsmanship|شغل إيد نضيف|إتقان الحرفة|savoir-faire|Handwerkskunst|artesanía|maestria artigiana|maestria artesanal`},
        {name: 'Music', entries: `objects|violin|كمان|كمان|violon|Geige|violín|violino|violino
objects|cello|تشيلو|تشيلو|violoncelle|Cello|violonchelo|violoncello|violoncelo
objects|flute|فلوت|فلوت|flûte|Flöte|flauta|flauto|flauta
objects|trumpet|ترومبيت|بوق|trompette|Trompete|trompeta|tromba|trompete
objects|accordion|أكورديون|أكورديون|accordéon|Akkordeon|acordeón|fisarmonica|acordeão
objects|tambourine|رق|رقّ|tambourin|Tamburin|pandereta|tamburello|pandeiro
people|conductor|مايسترو|قائد أوركسترا|chef d'orchestre|Dirigent|director de orquesta|direttore d'orchestra|maestro
people|composer|ملحن|ملحن|compositeur|Komponist|compositor|compositore|compositor
people|choir|كورال|جوقة|chorale|Chor|coro|coro|coro
objects|sheet music|نوتة|نوتة موسيقية|partition|Notenblatt|partitura|spartito|partitura
concepts|melody|لحن|لحن|mélodie|Melodie|melodía|melodia|melodia
concepts|harmony|تناغم|انسجام موسيقي|harmonie|Harmonie|armonía|armonia|harmonia
concepts|rhythm|إيقاع|إيقاع|rythme|Rhythmus|ritmo|ritmo|ritmo
concepts|tempo|سرعة اللحن|سرعة الإيقاع|tempo|Tempo|tempo|tempo|andamento
concepts|chorus|لازمة|لازمة غنائية|refrain|Refrain|estribillo|ritornello|refrão
actions|hum|دندن|دندن|fredonner|summen|tararear|canticchiare|cantarolar
actions|tune|ظبّط الآلة|اضبط الآلة|accorder|stimmen|afinar|accordare|afinar
time|rehearsal|بروفة|تدريب موسيقي|répétition|Probe|ensayo|prova|ensaio`},
        {name: 'Theatre', entries: `places|backstage|كواليس|كواليس|coulisses|Hinterbühne|bastidores|dietro le quinte|bastidores
places|auditorium|صالة الجمهور|قاعة المسرح|salle de spectacle|Zuschauerraum|auditorio|platea|auditório
objects|spotlight|سبوت نور|كشاف مسرحي|projecteur|Scheinwerfer|foco|riflettore|holofote
objects|costume|هدوم المسرحية|زي مسرحي|costume de scène|Bühnenkostüm|vestuario teatral|costume di scena|figurino
objects|stage prop|إكسسوار مسرح|أداة مسرحية|accessoire de scène|Requisite|utilería|oggetto di scena|adereço cênico
people|playwright|كاتب مسرحي|كاتب مسرحي|dramaturge|Dramatiker|dramaturgo|drammaturgo|dramaturgo
people|director|مخرج|مخرج|metteur en scène|Regisseur|director de escena|regista|diretor teatral
people|understudy|ممثل بديل|ممثل بديل|doublure|Ersatzdarsteller|suplente|sostituto|substituto
concepts|monologue|مونولوج|مناجاة مسرحية|monologue|Monolog|monólogo|monologo|monólogo
concepts|dialogue|حوار|حوار|dialogue|Dialog|diálogo|dialogo|diálogo
concepts|plot twist|مفاجأة في الحكاية|تحول في الحبكة|rebondissement|Wendung|giro argumental|colpo di scena|reviravolta
actions|improvise|ارتجل|ارتجل|improviser|improvisieren|improvisar|improvvisare|improvisar
concepts|comedy|كوميديا|ملهاة|comédie|Komödie|comedia|commedia|comédia
concepts|tragedy|تراجيديا|مأساة|tragédie|Tragödie|tragedia|tragedia|tragédia
concepts|encore|كمان مرة|فقرة إضافية|rappel|Zugabe|bis|bis|bis
actions|bow|انحني للجمهور|انحنِ|saluer|sich verbeugen|hacer una reverencia|inchinarsi|fazer uma reverência
feelings|stage fright|رهبة المسرح|رهبة المسرح|trac|Lampenfieber|miedo escénico|paura del palcoscenico|medo de palco
time|interval|بريك العرض|استراحة العرض|entracte|Pause im Stück|entreacto|intervallo|intervalo`},
        {name: 'Photography', entries: `technology|tripod|حامل كاميرا|حامل ثلاثي|trépied|Stativ|trípode|treppiede|tripé
technology|shutter|شاتر الكاميرا|غالق الكاميرا|obturateur|Verschluss|obturador|otturatore|obturador
technology|aperture|فتحة عدسة|فتحة عدسة|diaphragme|Blende|apertura|diaframma|abertura
technology|flash|فلاش|ومضة تصوير|flash|Blitz|flash|flash|flash
technology|zoom|زوم|تقريب بصري|zoom|Zoom|zoom|zoom|zoom
technology|memory card|كارت ميموري|بطاقة ذاكرة|carte mémoire|Speicherkarte|tarjeta de memoria|scheda di memoria|cartão de memória
concepts|focus|فوكس|ضبط البؤرة|mise au point|Fokus|enfoque|messa a fuoco|foco
concepts|exposure|تعريض|تعريض ضوئي|exposition photographique|Belichtung|exposición fotográfica|esposizione fotografica|exposição fotográfica
concepts|composition|تكوين الصورة|تكوين الصورة|composition photographique|Bildaufbau|composición fotográfica|composizione fotografica|composição fotográfica
concepts|silhouette|خيال الشخص|صورة ظلية|silhouette|Silhouette|silueta|sagoma|silhueta
concepts|reflection|انعكاس|انعكاس|reflet|Spiegelung|reflejo|riflesso|reflexo
actions|crop|قصّ الصورة|اقتصّ الصورة|recadrer|zuschneiden|recortar|ritagliare|recortar
places|darkroom|أوضة تحميض|غرفة تحميض|chambre noire|Dunkelkammer|cuarto oscuro|camera oscura|câmara escura
objects|negative|نيجاتيف|صورة سالبة|négatif|Negativ|negativo|negativo|negativo
objects|contact sheet|ورقة صور مصغرة|ورقة صور مصغرة|planche-contact|Kontaktabzug|hoja de contactos|provino a contatto|folha de contato
actions|develop film|حمّض الفيلم|حمّض الفيلم|développer un film|einen Film entwickeln|revelar película|sviluppare una pellicola|revelar filme
people|portrait sitter|حد بيتصور|شخص أمام العدسة|personne photographiée|Porträtmodell|persona retratada|persona ritratta|pessoa retratada
time|golden hour|الساعة الدهبية|الساعة الذهبية|heure dorée|goldene Stunde|hora dorada|ora dorata|hora dourada`},
    ]},
    {name: 'Outdoors', scenes: [
        {name: 'Forest', entries: `nature|oak|بلوط|بلوط|chêne|Eiche|roble|quercia|carvalho
nature|beech|زان|زان|hêtre|Buche|haya|faggio|faia
nature|birch|بتولا|بتولا|bouleau|Birke|abedul|betulla|bétula
nature|fern|سرخس|سرخس|fougère|Farn|helecho|felce|samambaia
nature|moss|حزاز|حزاز|mousse|Moos|musgo|muschio|musgo
nature|lichen|أشنة|أشنة|lichen|Flechte|liquen|lichene|líquen
nature|acorn|ثمرة بلوط|ثمرة بلوط|gland|Eichel|bellota|ghianda|bolota
nature|pine cone|كوز صنوبر|مخروط صنوبر|pomme de pin|Kiefernzapfen|piña de pino|pigna|pinha
nature|tree bark|قشرة شجرة|لحاء|écorce|Baumrinde|corteza de árbol|corteccia|casca de árvore
nature|tree stump|جذع مقطوع|جذع مقطوع|souche|Baumstumpf|tocón|ceppo|toco
nature|undergrowth|زرع تحت الشجر|نباتات تحت الأشجار|sous-bois|Unterholz|sotobosque|sottobosco|vegetação rasteira
nature|canopy|سقف من ورق الشجر|مظلة الأشجار|canopée|Blätterdach|dosel forestal|chioma forestale|copa das árvores
animals|woodpecker|نقار الخشب|نقار الخشب|pic|Specht|pájaro carpintero|picchio|pica-pau
animals|badger|غرير|غرير|blaireau|Dachs|tejón|tasso|texugo
animals|hedgehog|قنفد|قنفذ|hérisson|Igel|erizo|riccio|ouriço
animals|deer|أيل|أيل|cerf|Hirsch|ciervo|cervo|veado
actions|forage|دوّر على أكل|ابحث عن طعام|chercher de la nourriture|Nahrung suchen|buscar alimento|cercare cibo|procurar alimento
feelings|tranquillity|راحة بال|سكينة|tranquillité|Beschaulichkeit|tranquilidad serena|serenità|serenidade`},
        {name: 'Mountains', entries: `nature|summit|قمة|قمة|sommet|Gipfel|cumbre|vetta|cume
nature|ridge|حافة جبل|حافة جبلية|crête|Berggrat|cresta|cresta|crista
nature|ravine|وادي ضيق|أخدود|ravin|Schlucht|barranco|burrone|ravina
nature|cliff face|وش الجبل|جدار صخري|paroi rocheuse|Felswand|pared rocosa|parete rocciosa|parede rochosa
nature|glacier|نهر جليدي|نهر جليدي|glacier|Gletscher|glaciar|ghiacciaio|geleira
nature|avalanche|انهيار تلجي|انهيار ثلجي|avalanche|Lawine|avalancha|valanga|avalanche
objects|climbing harness|حزام تسلق|حزام تسلق|baudrier|Klettergurt|arnés de escalada|imbracatura|cadeirinha de escalada
objects|carabiner|مشبك تسلق|حلقة تسلق|mousqueton|Karabiner|mosquetón|moschettone|mosquetão
objects|crampons|مسامير تسلّق|مسامير تسلق|crampons|Steigeisen|crampones|ramponi|crampons
objects|trekking pole|عصاية للمشي|عصا مشي|bâton de marche|Wanderstock|bastón de senderismo|bastone da trekking|bastão de caminhada
people|mountaineer|متسلق جبال|متسلق جبال|alpiniste|Bergsteiger|montañero|alpinista|alpinista
actions|scramble|اتسلق الصخور|تسلّق الصخور|escalader les rochers|über Felsen klettern|trepar por rocas|arrampicarsi sulle rocce|escalar rochas
places|mountain hut|كوخ جبلي|كوخ جبلي|refuge|Berghütte|refugio de montaña|rifugio|abrigo de montanha
objects|sleeping bag|كيس نوم|كيس نوم|sac de couchage|Schlafsack|saco de dormir|sacco a pelo|saco de dormir
technology|head torch|كشاف راس|مصباح للرأس|lampe frontale|Stirnlampe|linterna frontal|lampada frontale|lanterna de cabeça
concepts|acclimatization|تعود على الارتفاع|تأقلم مع الارتفاع|acclimatation|Akklimatisierung|aclimatación|acclimatamento|aclimatação
feelings|determination|عزيمة|عزيمة|détermination|Entschlossenheit|determinación|determinazione|determinação
concepts|achievement|إنجاز|إنجاز|accomplissement|Leistung|logro|traguardo|conquista`},
        {name: 'Desert', entries: `nature|mirage|سراب|سراب|mirage|Fata Morgana|espejismo|miraggio|miragem
nature|sandstorm|عاصفة رمل|عاصفة رملية|tempête de sable|Sandsturm|tormenta de arena|tempesta di sabbia|tempestade de areia
nature|rock arch|قوس صخري|قوس صخري|arche rocheuse|Felsbogen|arco rocoso|arco roccioso|arco rochoso
nature|salt flat|أرض مالحة|مسطح ملحي|désert de sel|Salzebene|salar|distesa salina|planície salina
nature|cactus|صبار|صبار|cactus|Kaktus|cactus|cactus|cacto
nature|date palm|نخلة تمر|نخلة تمر|palmier dattier|Dattelpalme|palmera datilera|palma da dattero|tamareira
animals|fennec|ثعلب صحراوي|فنك|fennec|Fennek|fénec|fennec|feneco
animals|scorpion|عقرب|عقرب|scorpion|Skorpion|escorpión|scorpione|escorpião
animals|beetle|خنفسة|خنفساء|scarabée|Käfer|escarabajo|scarabeo|besouro
animals|lizard|سحلية|سحلية|lézard|Eidechse|lagarto|lucertola|lagarto
people|nomad|بدوي رحّال|رحّال|nomade|Nomade|nómada|nomade|nômade
objects|waterskin|قربة|قربة ماء|outre|Wasserschlauch|odre|otre|odre
objects|turban|عمة|عمامة|turban|Turban|turbante|turbante|turbante
objects|tent peg|وتد خيمة|وتد خيمة|piquet de tente|Hering|piqueta de tienda|picchetto|estaca de barraca
objects|camp stove|بوتاجاز رحلات|موقد تخييم|réchaud|Campingkocher|hornillo|fornello da campo|fogareiro
actions|ration|وفّر في الأكل|وزّع المؤن بحساب|rationner|rationieren|racionar|razionare|racionar
feelings|thirst|عطش|عطش|soif|Durst|sed|sete|sede
concepts|endurance|تحمل|تحمل|endurance|Ausdauer|resistencia|resistenza|resistência`},
        {name: 'River', entries: `nature|tributary|فرع نهر|رافد|affluent|Nebenfluss|afluente|affluente|afluente
nature|riverbank|ضفة|ضفة|berge|Flussufer|ribera|riva|margem
nature|riverbed|قاع نهر|قاع نهر|lit de rivière|Flussbett|lecho del río|letto del fiume|leito do rio
nature|current|تيار ميّه|تيار مائي|courant|Strömung|corriente|corrente|correnteza
nature|reed|بوص|قصب|roseau|Schilf|junco|canna|junco
nature|water lily|زنبق ميّه|زنبق ماء|nénuphar|Seerose|nenúfar|ninfea|nenúfar
animals|otter|قضاعة|قضاعة|loutre|Fischotter|nutria|lontra|lontra
animals|beaver|قندس|قندس|castor|Biber|castor|castoro|castor
animals|kingfisher|رفراف|رفراف|martin-pêcheur|Eisvogel|martín pescador|martin pescatore|martim-pescador
animals|heron|مالك حزين|مالك الحزين|héron|Reiher|garza|airone|garça
animals|dragonfly|يعسوب|يعسوب|libellule|Libelle|libélula|libellula|libélula
animals|tadpole|أبو ذنيبة|شرغوف|têtard|Kaulquappe|renacuajo|girino|girino
objects|paddle|مجداف قصير|مجداف قصير|pagaie|Paddel|remo de pala|pagaia|remo de pá
objects|canoe|كانو|زورق كانو|canoë|Kanu|canoa|canoa|canoa
technology|watermill|طاحونة ميّه|طاحونة مائية|moulin à eau|Wassermühle|molino de agua|mulino ad acqua|moinho de água
places|weir|سد صغير|هدار|déversoir|Wehr|azud|sbarramento|açude
concepts|irrigation|ري|ري|irrigation|Bewässerung|riego|irrigazione|irrigação
actions|drift|سيب نفسك للتيار|انجرف مع التيار|dériver|dahintreiben|ir a la deriva|andare alla deriva|ir à deriva`},
        {name: 'Farm', entries: `people|shepherd|راعي غنم|راعي أغنام|berger|Hirte|pastor|pastore|pastor
people|beekeeper|نحال|نحّال|apiculteur|Imker|apicultor|apicoltore|apicultor
places|barn|حظيرة|حظيرة|grange|Scheune|granero|fienile|celeiro
places|greenhouse|صوبة|دفيئة|serre|Gewächshaus|invernadero|serra|estufa
objects|pitchfork|مذراة|مذراة|fourche|Heugabel|horca|forcone|forcado
technology|tractor|جرار زراعي|جرار زراعي|tracteur|Traktor|tractor|trattore|trator
animals|calf|عجل|عجل|veau|Kalb|ternero|vitello|bezerro
animals|foal|مهر|مهر|poulain|Fohlen|potro|puledro|potro
animals|lamb|خروف صغير|حَمَل|agneau|Lamm|cordero|agnello|cordeiro
animals|kid goat|جدي|جدي|chevreau|Zicklein|cabrito|capretto|cabrito
animals|rooster|ديك|ديك|coq|Hahn|gallo|gallo|galo
animals|gosling|فرخ وز|فرخ إوز|oison|Gänseküken|ansarino|pulcino d'oca|filhote de ganso
nature|hay|دريس|دريس|foin|Heu|heno|fieno|feno
nature|straw|قش|قش|paille|Stroh|paja|paglia|palha
nature|compost|سماد عضوي|سماد عضوي|compost|Kompost|compost|compost|composto orgânico
actions|sow|ازرع البذور|ابذر|semer|säen|sembrar|seminare|semear
actions|harvest|احصد|احصد|récolter|ernten|cosechar|mietere|colher a safra
concepts|fertility|خصوبة|خصوبة|fertilité|Fruchtbarkeit|fertilidad|fertilità|fertilidade`},
        {name: 'Weather', entries: `nature|drizzle|رشة مطر|رذاذ|bruine|Nieselregen|llovizna|pioggerellina|garoa
nature|downpour|مطر غزير|مطر غزير|averse|Wolkenbruch|aguacero|acquazzone|pancada de chuva
nature|hail|حبات بَرَد|بَرَد|grêle|Hagel|granizo|grandine|granizo
nature|sleet|مطر بتلج|مطر ممزوج بالثلج|neige fondue|Schneeregen|aguanieve|nevischio|chuva com neve
nature|frost|صقيع|صقيع|gelée|Frost|escarcha|brina|geada
nature|dew|ندى|ندى|rosée|Tau|rocío|rugiada|orvalho
nature|thundercloud|سحابة رعد|سحابة رعدية|nuage d'orage|Gewitterwolke|nubarrón|nuvola temporalesca|nuvem de tempestade
nature|lightning bolt|صاعقة|صاعقة|éclair|Blitzschlag|rayo|fulmine|raio
nature|gust|هبة هوا|هبة ريح|rafale|Windböe|ráfaga|raffica|rajada
nature|heatwave|موجة حر|موجة حر|canicule|Hitzewelle|ola de calor|ondata di caldo|onda de calor
nature|cold snap|موجة برد|موجة برد|vague de froid|Kälteeinbruch|ola de frío|ondata di freddo|onda de frio
nature|humidity|رطوبة|رطوبة|humidité|Luftfeuchtigkeit|humedad|umidità|umidade
people|meteorologist|خبير أرصاد|خبير أرصاد|météorologue|Meteorologe|meteorólogo|meteorologo|meteorologista
technology|barometer|بارومتر|مقياس الضغط الجوي|baromètre|Barometer|barómetro|barometro|barômetro
technology|rain gauge|مقياس مطر|مقياس مطر|pluviomètre|Regenmesser|pluviómetro|pluviometro|pluviômetro
work|weather forecast|نشرة الجو|نشرة جوية|prévisions météo|Wettervorhersage|pronóstico del tiempo|previsioni del tempo|previsão do tempo
concepts|visibility|مدى الرؤية|مدى الرؤية|visibilité|Sichtweite|visibilidad|visibilità|visibilidade
time|sunset glow|حمرة الغروب|وهج الغروب|crépuscule rouge|Abendrot|resplandor del atardecer|rossore del tramonto|clarão do entardecer`},
    ]},
    {name: 'Work and invention', scenes: [
        {name: 'Office', entries: `people|colleague|زميل شغل|زميل عمل|collègue|Kollege|compañero de trabajo|collega|colega de trabalho
people|supervisor|مشرف|مشرف|responsable|Vorgesetzter|supervisor|supervisore|supervisor
people|intern|متدرب|متدرب|stagiaire|Praktikant|becario|tirocinante|estagiário
work|agenda|أجندة اجتماع|جدول أعمال الاجتماع|ordre du jour|Tagesordnung|orden del día|ordine del giorno|pauta
work|minutes of meeting|محضر اجتماع|محضر اجتماع|compte rendu|Sitzungsprotokoll|acta|verbale|ata
work|presentation|عرض تقديمي|عرض تقديمي|présentation|Präsentation|presentación|presentazione|apresentação
work|invoice|فاتورة شغل|فاتورة تجارية|facture commerciale|Geschäftsrechnung|factura comercial|fattura commerciale|fatura comercial
work|budget|ميزانية|ميزانية|budget|Budget|presupuesto|bilancio|orçamento
work|expense claim|طلب رد المصاريف|طلب تعويض نفقات|note de frais|Spesenabrechnung|nota de gastos|nota spese|reembolso de despesas
work|payslip|مفردات مرتب|قسيمة راتب|fiche de paie|Gehaltsabrechnung|nómina|busta paga|contracheque
work|annual leave|إجازة سنوية|إجازة سنوية|congé annuel|Jahresurlaub|vacaciones anuales|ferie annuali|férias anuais
actions|delegate|فوّض|فوّض|déléguer|delegieren|delegar|delegare|delegar
objects|stapler|دباسة|دباسة|agrafeuse|Hefter|grapadora|cucitrice|grampeador
objects|paper clip|مشبك ورق|مشبك ورق|trombone|Büroklammer|clip|graffetta|clipe
objects|filing cabinet|دولاب ملفات|خزانة ملفات|classeur métallique|Aktenschrank|archivador|schedario|arquivo
technology|shredder|فرامة ورق|فرّامة ورق|destructeur de documents|Aktenvernichter|trituradora de papel|tritadocumenti|fragmentadora
actions|prioritize|ابدأ بالأهم|رتّب الأولويات|hiérarchiser|priorisieren|priorizar|stabilire le priorità|priorizar
concepts|efficiency|كفاءة|كفاءة|efficacité|Effizienz|eficiencia|efficienza|eficiência`},
        {name: 'Computing', entries: `technology|mouse pointer|سهم الماوس|مؤشر الفأرة|pointeur|Mauszeiger|puntero|puntatore|ponteiro
technology|touchpad|تاتش باد|لوحة لمس|pavé tactile|Touchpad|panel táctil|touchpad|touchpad
technology|webcam|كاميرا ويب|كاميرا ويب|webcam|Webcam|cámara web|webcam|webcam
technology|router|راوتر|موجّه شبكة|routeur|Router|rúter|router|roteador
technology|hard drive|هارد|قرص صلب|disque dur|Festplatte|disco duro|disco rigido|disco rígido
technology|USB stick|فلاشة|ذاكرة فلاش|clé USB|USB-Stick|memoria USB|chiavetta USB|pen drive
technology|spreadsheet|شيت إكسل|جدول بيانات|tableur|Tabellenkalkulation|hoja de cálculo|foglio elettronico|planilha
technology|browser tab|تاب المتصفح|علامة تبويب|onglet|Browser-Tab|pestaña|scheda del browser|aba do navegador
technology|download|تنزيل|تنزيل|téléchargement|Download|descarga|scaricamento|download
technology|backup|نسخة احتياطية|نسخة احتياطية|sauvegarde|Sicherungskopie|copia de seguridad|copia di sicurezza|cópia de segurança
technology|encryption|تشفير|تشفير|chiffrement|Verschlüsselung|cifrado|cifratura|criptografia
technology|firewall|فايروول|جدار حماية|pare-feu|Firewall|cortafuegos|firewall|firewall
people|programmer|مبرمج|مبرمج|programmeur|Programmierer|programador|programmatore|programador
work|bug report|بلاغ عن مشكلة|تقرير خطأ|rapport de bogue|Fehlerbericht|informe de error|segnalazione di errore|relatório de erro
actions|restart|اعمل ريستارت|أعد التشغيل|redémarrer|neu starten|reiniciar|riavviare|reiniciar
actions|scroll|اعمل سكرول|مرّر الصفحة|faire défiler|scrollen|desplazarse|scorrere|rolar a página
concepts|privacy|خصوصية|خصوصية|confidentialité|Privatsphäre|privacidad|riservatezza|privacidade
concepts|connection|اتصال|اتصال|connexion|Verbindung|conexión|connessione|conexão`},
        {name: 'Engineering', entries: `technology|piston|بستم|مكبس|piston|Kolben|pistón|pistone|pistão
technology|cogwheel|ترس|ترس|roue dentée|Zahnrad|rueda dentada|ruota dentata|engrenagem
technology|ball bearing|رولمان بلي|محمل كروي|roulement à billes|Kugellager|rodamiento de bolas|cuscinetto a sfere|rolamento de esferas
technology|valve|صمام|صمام|vanne|Ventil|válvula|valvola|válvula
technology|turbine|توربينة|توربين|turbine|Turbine|turbina|turbina|turbina
technology|generator|مولد|مولد|générateur|Generator|generador|generatore|gerador
people|engineer|مهندس|مهندس|ingénieur|Ingenieur|ingeniero|ingegnere|engenheiro
people|technician|فني|فني|technicien|Techniker|técnico|tecnico|técnico
work|prototype|نموذج تجريبي|نموذج أولي|prototype|Prototyp|prototipo|prototipo|protótipo
work|technical drawing|رسم هندسي|رسم هندسي|dessin technique|technische Zeichnung|dibujo técnico|disegno tecnico|desenho técnico
work|test bench|منصة اختبار|منصة اختبار|banc d'essai|Prüfstand|banco de pruebas|banco di prova|bancada de testes
actions|calibrate|عاير الجهاز|عاير الجهاز|étalonner|kalibrieren|calibrar|calibrare|calibrar
concepts|friction|احتكاك|احتكاك|frottement|Reibung|fricción|attrito|atrito
concepts|torque|عزم|عزم دوران|couple mécanique|Drehmoment|par de torsión|coppia|torque
concepts|tension|شد|قوة الشد|tension mécanique|Zugspannung|tensión mecánica|tensione meccanica|tensão mecânica
concepts|precision|دقة|دقة|précision|Präzision|precisión|precisione|precisão
actions|assemble|ركّب الأجزاء|جمّع الأجزاء|assembler|zusammenbauen|ensamblar|assemblare|montar
concepts|innovation|ابتكار|ابتكار|innovation|Innovation|innovación|innovazione|inovação`},
        {name: 'Construction', entries: `people|bricklayer|بنّا|بنّاء|maçon|Maurer|albañil|muratore|pedreiro
people|surveyor|مسّاح|مسّاح|géomètre|Vermessungsingenieur|topógrafo|geometra|topógrafo
places|building site|موقع بنا|موقع بناء|chantier|Baustelle|obra|cantiere edile|canteiro de obras
objects|scaffolding|سقالة|سقالة|échafaudage|Gerüst|andamio|impalcatura|andaime
technology|cement mixer|خلاطة أسمنت|خلاطة إسمنت|bétonnière|Betonmischer|hormigonera|betoniera|betoneira
objects|wheelbarrow|برويطة|عربة يد|brouette|Schubkarre|carretilla|carriola|carrinho de mão
objects|brick|طوبة|طوبة|brique|Ziegel|ladrillo|mattone|tijolo
objects|cement|أسمنت|إسمنت|ciment|Zement|cemento|cemento|cimento
objects|concrete|خرسانة|خرسانة|béton|Beton|hormigón|calcestruzzo|concreto
objects|trowel|مسطرين|مسطرين|truelle|Maurerkelle|paleta de albañil|cazzuola|colher de pedreiro
objects|hard hat|خوذة أمان|خوذة سلامة|casque de chantier|Schutzhelm|casco de obra|casco da cantiere|capacete de segurança
actions|lay bricks|رصّ الطوب|رصّ الطوب|poser des briques|mauern|colocar ladrillos|posare mattoni|assentar tijolos
concepts|foundation|أساس|أساس|fondation|Fundament|cimientos|fondamenta|fundação
objects|beam|كمرة|عارضة|poutre|Balken|viga|trave|viga
objects|pillar|عمود|عمود|pilier|Säule|pilar|pilastro|pilar
actions|reinforce|دعّم|دعّم|renforcer|verstärken|reforzar|rinforzare|reforçar
work|building permit|رخصة بنا|رخصة بناء|permis de construire|Baugenehmigung|licencia de obra|permesso edilizio|alvará de construção
concepts|stability|ثبات|ثبات|stabilité|Stabilität|estabilidad|stabilità|estabilidade`},
        {name: 'Laboratory', entries: `people|researcher|باحث|باحث|chercheur|Forscher|investigador|ricercatore|pesquisador
objects|test tube|أنبوبة اختبار|أنبوب اختبار|tube à essai|Reagenzglas|tubo de ensayo|provetta|tubo de ensaio
objects|pipette|ماصة|ماصة|pipette|Pipette|pipeta|pipetta|pipeta
objects|beaker|بيكر|كأس مخبري|bécher|Becherglas|vaso de precipitados|becher|béquer
objects|Petri dish|طبق بتري|طبق بتري|boîte de Petri|Petrischale|placa de Petri|piastra di Petri|placa de Petri
technology|microscope|ميكروسكوب|مجهر|microscope|Mikroskop|microscopio|microscopio|microscópio
concepts|hypothesis|فرضية|فرضية|hypothèse|Hypothese|hipótesis|ipotesi|hipótese
work|sample|عينة|عينة|échantillon|Probe|muestra|campione|amostra
work|observation|ملاحظة|ملاحظة|observation|Beobachtung|observación|osservazione|observação
work|measurement|قياس|قياس|mesure|Messung|medición|misurazione|medição
work|finding|نتيجة بحث|نتيجة بحث|résultat de recherche|Forschungsergebnis|hallazgo|risultato della ricerca|resultado de pesquisa
actions|compare|قارن|قارن|comparer|vergleichen|comparar|confrontare|comparar
concepts|molecule|جزيء|جزيء|molécule|Molekül|molécula|molecola|molécula
concepts|atom|ذرة|ذرة|atome|Atom|átomo|atomo|átomo
concepts|reaction|تفاعل|تفاعل|réaction|Reaktion|reacción|reazione|reação
actions|dissolve|دوّب|أذب|dissoudre|auflösen|disolver|sciogliere|dissolver
concepts|evidence|دليل|برهان|preuve|Beleg|evidencia|prova scientifica|evidência
feelings|intellectual curiosity|فضول علمي|فضول علمي|curiosité scientifique|Wissensdurst|curiosidad científica|curiosità scientifica|curiosidade científica`},
        {name: 'Space flight', entries: `people|astronaut|رائد فضاء|رائد فضاء|astronaute|Astronaut|astronauta|astronauta|astronauta
technology|space shuttle|مكوك فضاء|مكوك فضائي|navette spatiale|Raumfähre|transbordador espacial|navetta spaziale|ônibus espacial
technology|rocket booster|صاروخ دفع|صاروخ مساعد|propulseur|Zusatzrakete|cohete propulsor|razzo propulsore|foguete propulsor
technology|space station|محطة فضاء|محطة فضائية|station spatiale|Raumstation|estación espacial|stazione spaziale|estação espacial
objects|spacesuit|بدلة فضاء|بدلة فضاء|combinaison spatiale|Raumanzug|traje espacial|tuta spaziale|traje espacial
time|countdown|عد تنازلي|عد تنازلي|compte à rebours|Countdown|cuenta atrás|conto alla rovescia|contagem regressiva
nature|asteroid|كويكب|كويكب|astéroïde|Asteroid|asteroide|asteroide|asteroide
nature|comet|مذنب|مذنب|comète|Komet|cometa|cometa|cometa
nature|meteorite|نيزك|نيزك|météorite|Meteorit|meteorito|meteorite|meteorito
nature|crater|فوهة|فوهة|cratère|Krater|cráter|cratere|cratera
nature|nebula|سديم|سديم|nébuleuse|Nebel im All|nebulosa|nebulosa|nebulosa
nature|galaxy|مجرة|مجرة|galaxie|Galaxie|galaxia|galassia|galáxia
concepts|weightlessness|انعدام الوزن|انعدام الوزن|apesanteur|Schwerelosigkeit|ingravidez|assenza di peso|ausência de peso
technology|solar panel|لوح شمسي|لوح شمسي|panneau solaire|Solarpanel|panel solar|pannello solare|painel solar
technology|rover|عربية استكشاف|عربة جوالة|robot explorateur|Erkundungsrover|vehículo explorador|rover|veículo explorador
actions|dock in space|التحام مركبتين|التحام فضائي|s'amarrer dans l'espace|im All andocken|acoplar en el espacio|attraccare nello spazio|acoplar no espaço
concepts|exploration|استكشاف|استكشاف|exploration|Erkundung|exploración|esplorazione|exploração
feelings|wonder at space|مبهور بالفضاء|انبهار بالفضاء|émerveillement cosmique|Staunen über das All|asombro cósmico|meraviglia cosmica|encanto cósmico`},
    ]},
    {name: 'Adventure', scenes: [
        {name: 'Castle', entries: `places|drawbridge|كوبري متحرك|جسر متحرك|pont-levis|Zugbrücke|puente levadizo|ponte levatoio|ponte levadiça
places|moat|خندق ميّه|خندق مائي|douves|Burggraben|foso|fossato|fosso
places|battlements|فتحات سور القلعة|شرفات القلعة|créneaux|Zinnen|almenas|merli|ameias
places|dungeon|زنزانة في القلعة|سجن تحت القلعة|cachot|Burgverlies|mazmorra|segreta|masmorra
objects|portcullis|بوابة حديد بتنزل|بوابة حديدية هابطة|herse|Fallgatter|rastrillo|saracinesca|grade levadiça
objects|tapestry|سجادة للحيطة|سجادة جدارية|tapisserie|Wandteppich|tapiz|arazzo|tapeçaria
people|knight|فارس|فارس|chevalier|Ritter|caballero|cavaliere|cavaleiro
people|squire|مساعد الفارس|حامل درع|écuyer|Knappe|escudero|scudiero|escudeiro
people|herald|منادي القلعة|منادٍ|héraut|Herold|heraldo|araldo|arauto
objects|shield|ترس حرب|درع|bouclier|Schild|escudo|scudo|escudo
objects|coat of arms|شعار العيلة|شعار نبالة|armoiries|Wappen|escudo de armas|stemma|brasão
concepts|allegiance|ولاء|ولاء|allégeance|Treuepflicht|lealtad|fedeltà|lealdade
places|banquet hall|قاعة العزومة|قاعة ولائم|salle de banquet|Festsaal|salón de banquetes|sala dei banchetti|salão de banquetes
objects|goblet|كاس برجل|كأس ذات ساق|coupe|Kelch|cáliz|calice|cálice
objects|candelabrum|شمعدان|شمعدان|candélabre|Kerzenleuchter|candelabro|candelabro|candelabro
actions|proclaim|أعلن|أعلن|proclamer|verkünden|proclamar|proclamare|proclamar
concepts|legend|أسطورة|أسطورة|légende|Legende|leyenda|leggenda|lenda
concepts|quest|رحلة بحث|رحلة بحث|quête|Queste|búsqueda heroica|ricerca eroica|busca heroica`},
        {name: 'Cave exploration', entries: `places|cavern|مغارة|مغارة|caverne|Höhle|caverna|caverna|caverna
nature|stalactite|صخور مدلدلة|هوابط الكهوف|stalactite|Stalaktit|estalactita|stalattite|estalactite
nature|stalagmite|عمود صخري|صواعد الكهوف|stalagmite|Stalagmit|estalagmita|stalagmite|estalagmite
nature|limestone|حجر جيري|حجر جيري|calcaire|Kalkstein|caliza|calcare|calcário
nature|underground lake|بحيرة تحت الأرض|بحيرة جوفية|lac souterrain|unterirdischer See|lago subterráneo|lago sotterraneo|lago subterrâneo
nature|echo|صدى|صدى|écho|Echo|eco|eco|eco
animals|bat|خفاش|خفاش|chauve-souris|Fledermaus|murciélago|pipistrello|morcego
animals|cave cricket|صرصار الكهف|جدجد الكهوف|grillon cavernicole|Höhlenheuschrecke|grillo de cueva|grillo delle caverne|grilo de caverna
people|caver|مستكشف كهوف|مستكشف كهوف|spéléologue|Höhlenforscher|espeleólogo|speleologo|espeleólogo
objects|safety rope|حبل أمان|حبل أمان|corde de sécurité|Sicherungsseil|cuerda de seguridad|corda di sicurezza|corda de segurança
objects|chalk mark|علامة طباشير|علامة طباشير|marque à la craie|Kreidemarkierung|marca de tiza|segno di gesso|marca de giz
actions|crawl|ازحف|ازحف|ramper|kriechen|gatear|strisciare|rastejar
places|narrow passage|ممر ضيق|ممر ضيق|passage étroit|Engstelle|paso estrecho|passaggio stretto|passagem estreita
nature|rock pool|بركة بين الصخور|بركة صخرية|bassin rocheux|Felsbecken|poza rocosa|pozza rocciosa|poça rochosa
nature|mineral deposit|رواسب معادن|رواسب معدنية|dépôt minéral|Mineralablagerung|depósito mineral|deposito minerale|depósito mineral
actions|squeeze through|ازنق نفسك وعدّي|مر عبر فجوة|se faufiler|sich durchzwängen|pasar por un hueco|infilarsi|passar por uma fresta
feelings|claustrophobia|خوف الأماكن الضيقة|رهاب الأماكن المغلقة|claustrophobie|Klaustrophobie|claustrofobia|claustrofobia|claustrofobia
concepts|way out|مخرج|مخرج|issue|Ausweg|salida|via d'uscita|saída`},
        {name: 'Detective mystery', entries: `people|detective|محقق خاص|محقق خاص|détective|Detektiv|detective|investigatore|detetive
people|witness|شاهد|شاهد|témoin|Zeuge|testigo|testimone|testemunha
people|suspect|مشتبه فيه|مشتبه به|suspect|Verdächtiger|sospechoso|sospettato|suspeito
concepts|alibi|كان في مكان تاني|حجة غياب|alibi|Alibi|coartada|alibi|álibi
objects|fingerprint|بصمة|بصمة|empreinte digitale|Fingerabdruck|huella dactilar|impronta digitale|impressão digital
objects|magnifying glass|عدسة مكبرة|عدسة مكبرة|loupe|Lupe|lupa|lente d'ingrandimento|lupa
work|case file|ملف قضية|ملف قضية|dossier d'enquête|Fallakte|expediente|fascicolo|arquivo do caso
concepts|motive|دافع|دافع|mobile|Tatmotiv|móvil|movente|motivo
concepts|contradiction|تناقض|تناقض|contradiction|Widerspruch|contradicción|contraddizione|contradição
concepts|red herring|دليل غلط|دليل مضلل|fausse piste|falsche Fährte|pista falsa|falsa pista|pista falsa
actions|deduce|استنتج|استنتج|déduire|schlussfolgern|deducir|dedurre|deduzir
actions|question someone|استجوب|استجوب|interroger|befragen|interrogar|interrogare|interrogar
objects|torn note|ورقة مقطوعة|رسالة ممزقة|billet déchiré|zerrissene Notiz|nota rasgada|biglietto strappato|bilhete rasgado
objects|missing jewel|جوهرة ضايعة|جوهرة مفقودة|bijou disparu|verschwundenes Juwel|joya desaparecida|gioiello scomparso|joia desaparecida
places|locked study|أوضة مكتب مقفولة|غرفة مكتب مغلقة|bureau fermé|verschlossenes Arbeitszimmer|despacho cerrado|studio chiuso|escritório trancado
actions|reconstruct|رتّب الأحداث|أعد بناء الأحداث|reconstituer|rekonstruieren|reconstruir|ricostruire|reconstituir
feelings|suspicion|شك في حد|ريبة|soupçon|Verdacht|sospecha|sospetto|suspeita
concepts|revelation|كشف الحقيقة|كشف الحقيقة|révélation|Enthüllung|revelación|rivelazione|revelação`},
        {name: 'Magical garden', entries: `people|wizard|ساحر|ساحر|magicien|Zauberer|mago|mago|mago
people|fairy|جنية|جنية|fée|Fee|hada|fata|fada
objects|wand|عصاية سحرية|عصا سحرية|baguette magique|Zauberstab|varita mágica|bacchetta magica|varinha mágica
objects|potion|مشروب سحري|جرعة سحرية|potion|Zaubertrank|poción|pozione|poção
objects|spellbook|كتاب تعاويذ|كتاب تعاويذ|grimoire|Zauberbuch|libro de hechizos|libro degli incantesimi|livro de feitiços
actions|enchant|اسحر|اسحر|ensorceler|verzaubern|hechizar|incantare|encantar
animals|unicorn|حصان بقرن|حصان أحادي القرن|licorne|Einhorn|unicornio|unicorno|unicórnio
animals|dragon|تنين|تنين|dragon|Drache|dragón|drago|dragão
animals|phoenix|عنقاء|عنقاء|phénix|Phönix|fénix|fenice|fênix
objects|invisibility cloak|عباية بتخفي|عباءة إخفاء|cape d'invisibilité|Tarnumhang|capa de invisibilidad|mantello dell'invisibilità|capa de invisibilidade
places|enchanted fountain|نافورة مسحورة|نافورة مسحورة|fontaine enchantée|verzauberter Brunnen|fuente encantada|fontana incantata|fonte encantada
concepts|transformation|تحول|تحول|métamorphose|Verwandlung|transformación|trasformazione|transformação
objects|crystal ball|كورة كريستال|كرة بلورية|boule de cristal|Kristallkugel|bola de cristal|sfera di cristallo|bola de cristal
concepts|prophecy|نبوءة|نبوءة|prophétie|Prophezeiung|profecía|profezia|profecia
concepts|riddle|فزورة|لغز لفظي|énigme|Rätsel|acertijo|indovinello|enigma
actions|grant a wish|حقّق أمنية|حقق أمنية|exaucer un vœu|einen Wunsch erfüllen|conceder un deseo|esaudire un desiderio|realizar um desejo
feelings|enchantment|سحر|سحر|enchantement|Verzauberung|encantamiento|incanto|encantamento
concepts|happy ending|نهاية سعيدة|نهاية سعيدة|fin heureuse|glückliches Ende|final feliz|lieto fine|final feliz`},
        {name: 'Expedition', entries: `people|explorer|رحّالة|رحالة|explorateur|Entdecker|explorador|esploratore|explorador
people|interpreter|مترجم|مترجم شفوي|interprète|Dolmetscher|intérprete|interprete|intérprete
objects|field journal|دفتر الرحلة|دفتر الرحلة|carnet de terrain|Feldtagebuch|diario de campo|diario di campo|diário de campo
objects|waterproof pouch|جراب ضد الميّه|جراب مضاد للماء|pochette étanche|wasserdichte Tasche|bolsa impermeable|custodia impermeabile|bolsa impermeável
objects|ration pack|أكل الرحلة|حقيبة مؤن|ration de survie|Verpflegungspaket|ración de supervivencia|razione di sopravvivenza|ração de sobrevivência
concepts|route planning|تخطيط الطريق|تخطيط المسار|planification d'itinéraire|Routenplanung|planificación de ruta|pianificazione del percorso|planejamento de rota
places|suspension bridge|كوبري معلق|جسر معلق|pont suspendu|Hängebrücke|puente colgante|ponte sospeso|ponte suspensa
nature|waterfall|شلال|شلال|cascade|Wasserfall|cascada|cascata|cachoeira
nature|jungle|أدغال|أدغال|jungle|Dschungel|jungla|giungla|selva
animals|toucan|طوقان|طوقان|toucan|Tukan|tucán|tucano|tucano
animals|macaw|بغبغان ماكاو|ببغاء المكاو|ara|Ara|guacamayo|ara|arara
actions|navigate|اعرف طريقك|اهتدِ إلى الطريق|s'orienter|sich orientieren|orientarse|orientarsi|se orientar
places|base camp|المعسكر الأساسي|المعسكر الأساسي|camp de base|Basislager|campamento base|campo base|acampamento base
technology|satellite phone|تليفون ساتلايت|هاتف بالأقمار الصناعية|téléphone satellite|Satellitentelefon|teléfono satelital|telefono satellitare|telefone via satélite
technology|beacon|جهاز تحديد مكان|جهاز إرسال إشارات|balise|Peilsender|baliza|segnalatore|baliza
actions|document|وثّق|وثّق|documenter|dokumentieren|documentar|documentare|documentar
concepts|resourcefulness|حسن تصرف|حسن التصرف|débrouillardise|Einfallsreichtum|ingenio|intraprendenza|desenvoltura
concepts|milestone|محطة مهمة|محطة بارزة|jalon|Meilenstein|hito|pietra miliare|marco`},
        {name: 'Campfire', entries: `objects|firewood|حطب|حطب|bois de chauffage|Brennholz|leña|legna da ardere|lenha
objects|kindling|حطب رفيع|عيدان إشعال|petit bois|Anzündholz|yesca|legnetti|gravetos
objects|flint|حجر صوان|حجر صوان|silex|Feuerstein|pedernal|selce|sílex
objects|matches|كبريت|أعواد ثقاب|allumettes|Streichhölzer|cerillas|fiammiferi|fósforos
nature|embers|جمر|جمر|braises|Glut|brasas|braci|brasas
nature|spark|شرارة|شرارة|étincelle|Funke|chispa|scintilla|faísca
objects|camp kettle|غلاية رحلات|غلاية تخييم|bouilloire de camping|Campingkessel|hervidor de camping|bollitore da campeggio|chaleira de acampamento
objects|skewer|سيخ|سيخ|brochette|Spieß|pincho|spiedino|espeto
food|roasted chestnuts|أبو فروة مشوي|كستناء مشوية|châtaignes grillées|geröstete Kastanien|castañas asadas|castagne arrosto|castanhas assadas
actions|toast|حمّص|حمّص|faire griller|rösten|tostar|tostare|torrar
actions|huddle|اتلمّوا جنب بعض|تجمّع|se blottir|zusammenrücken|acurrucarse|rannicchiarsi insieme|se aconchegar
feelings|warmth|دفا|دفء|chaleur|Wärme|calor|calore|calor
concepts|campfire tale|حكاية حوالين النار|حكاية حول النار|récit de veillée|Lagerfeuergeschichte|relato de fogata|racconto al falò|história de fogueira
concepts|folklore|حكايات شعبية|تراث شعبي|folklore|Folklore|folclore|folclore|folclore
time|nightfall|أول الليل|حلول الليل|tombée de la nuit|Einbruch der Nacht|anochecer|calar della notte|anoitecer
nature|constellation|مجموعة نجوم|كوكبة|constellation|Sternbild|constelación|costellazione|constelação
animals|cricket|صرصار الليل|صرصور الحقل|grillon|Grille|grillo|grillo|grilo
actions|stargaze|اتفرج على النجوم|راقب النجوم|observer les étoiles|Sterne beobachten|observar las estrellas|osservare le stelle|observar as estrelas`},
    ]},
    {name: 'Relationships', scenes: [
        {name: 'Friendship', entries: `people|best friend|أعز صاحب|أعز صديق|meilleur ami|bester Freund|mejor amigo|migliore amico|melhor amigo
people|pen pal|صاحب بالمراسلة|صديق مراسلة|correspondant|Brieffreund|amigo por correspondencia|amico di penna|amigo por correspondência
people|roommate|زميل سكن|زميل سكن|colocataire|Mitbewohner|compañero de piso|coinquilino|colega de apartamento
actions|introduce|عرّفهم ببعض|عرّف الآخرين ببعضهم|présenter quelqu'un|jemanden vorstellen|presentar a alguien|presentare qualcuno|apresentar alguém
actions|invite|اعزم|ادعُ|inviter|einladen|invitar|invitare|convidar
actions|keep in touch|خليك على تواصل|ابقَ على اتصال|garder le contact|in Kontakt bleiben|mantener el contacto|restare in contatto|manter contato
feelings|affection|محبة|مودة|affection|Zuneigung|afecto|affetto|afeição
feelings|fondness|معزّة|محبة|attachement tendre|Herzlichkeit|cariño|tenerezza affettuosa|carinho afetuoso
feelings|amusement|تسلية|تسلية|amusement|Belustigung|diversión|divertimento|divertimento
concepts|inside joke|إفيه بين صحاب|مزحة خاصة|plaisanterie privée|Insiderwitz|broma interna|battuta tra amici|piada interna
concepts|common ground|نقطة مشتركة|أرضية مشتركة|terrain d'entente|Gemeinsamkeit|punto en común|punto in comune|ponto em comum
concepts|companionship|ونس|صحبة|compagnie|Geselligkeit|compañía|compagnia|companhia
actions|confide|فضفض|بُح بسرّك|se confier|sich anvertrauen|confiarse|confidarsi|desabafar
actions|encourage|شجّع|شجّع|encourager|ermutigen|animar|incoraggiare|encorajar
actions|stand by someone|اقف جنب حد|ساند شخصا|soutenir quelqu'un|jemandem beistehen|apoyar a alguien|stare vicino a qualcuno|apoiar alguém
concepts|loyalty|إخلاص|إخلاص|fidélité|Loyalität|fidelidad|lealtà|fidelidade
concepts|mutual respect|احترام متبادل|احترام متبادل|respect mutuel|gegenseitiger Respekt|respeto mutuo|rispetto reciproco|respeito mútuo
time|long-awaited meeting|مقابلة بعد طول غياب|لقاء منتظر|rencontre attendue|lang ersehntes Treffen|encuentro esperado|incontro atteso|encontro esperado`},
        {name: 'Family gathering', entries: `people|grandmother|تيتة|جدة|grand-mère|Großmutter|abuela|nonna|avó
people|grandfather|جدو|جد|grand-père|Großvater|abuelo|nonno|avô
people|maternal uncle|خال|خال|oncle maternel|Onkel mütterlicherseits|tío materno|zio materno|tio materno
people|paternal aunt|عمة|عمة|tante paternelle|Tante väterlicherseits|tía paterna|zia paterna|tia paterna
people|nephew|ابن أخوك|ابن الأخ|neveu|Neffe|sobrino|nipote maschio|sobrinho
people|niece|بنت أخوك|ابنة الأخ|nièce|Nichte|sobrina|nipote femmina|sobrinha
people|cousin|ابن عمك|ابن العم|cousin|Cousin|primo|cugino|primo
people|mother-in-law|حماة|حماة|belle-mère|Schwiegermutter|suegra|suocera|sogra
people|brother-in-law|جوز أختك|زوج الأخت|beau-frère|Schwager|cuñado|cognato|cunhado
people|twins|توأم|توأم|jumeaux|Zwillinge|gemelos|gemelli|gêmeos
people|newborn|مولود|مولود|nouveau-né|Neugeborenes|recién nacido|neonato|recém-nascido
concepts|generation|جيل|جيل|génération|Generation|generación|generazione|geração
concepts|family tree|شجرة عيلة|شجرة عائلة|arbre généalogique|Stammbaum|árbol genealógico|albero genealogico|árvore genealógica
objects|heirloom|ورث من العيلة|إرث عائلي|objet de famille|Erbstück|reliquia familiar|cimelio di famiglia|herança de família
objects|baby blanket|بطانية بيبي|بطانية رضيع|couverture de bébé|Babydecke|manta de bebé|copertina da neonato|manta de bebê
actions|cuddle|احضن|عانق|câliner|kuscheln|abrazar con cariño|coccolare|aconchegar
feelings|tenderness|حنية|حنان|tendresse|Zärtlichkeit|ternura|tenerezza|ternura
concepts|family bond|رباط العيلة|رابطة عائلية|lien familial|Familienband|vínculo familiar|legame familiare|laço familiar`},
        {name: 'Moving into a neighbourhood', entries: `people|newcomer|جار جديد|وافد جديد|nouvel arrivant|Neuankömmling|recién llegado|nuovo arrivato|recém-chegado
people|landlord|صاحب البيت|مالك المنزل|propriétaire|Vermieter|casero|padrone di casa|senhorio
people|tenant|مستأجر|مستأجر|locataire|Mieter|inquilino|inquilino|inquilino
work|lease|عقد إيجار|عقد إيجار|bail|Mietvertrag|contrato de alquiler|contratto d'affitto|contrato de aluguel
work|deposit|تأمين إيجار|تأمين إيجار|caution locative|Kaution|fianza de alquiler|caparra|caução
objects|moving box|كرتونة نقل|صندوق نقل|carton de déménagement|Umzugskarton|caja de mudanza|scatolone|caixa de mudança
work|change of address|تغيير عنوان|تغيير عنوان|changement d'adresse|Adressänderung|cambio de dirección|cambio di indirizzo|mudança de endereço
objects|doorbell|جرس باب|جرس باب|sonnette|Türklingel|timbre de puerta|campanello della porta|campainha da porta
objects|nameplate|يافطة بالاسم|لوحة الاسم|plaque nominative|Namensschild|placa del nombre|targhetta|placa de nome
actions|settle in|اتعوّد على المكان|استقر في المكان|s'installer|sich einleben|instalarse|ambientarsi|se adaptar ao lugar
actions|lend|سلّف|أعِر|prêter|verleihen|prestar|prestare|emprestar
concepts|housewarming|عزومة البيت الجديد|احتفال بمنزل جديد|pendaison de crémaillère|Einweihungsfeier|fiesta de inauguración|festa di inaugurazione|festa de casa nova
feelings|homesickness|وحشة البيت|حنين إلى الوطن|mal du pays|Heimweh|añoranza del hogar|nostalgia di casa|saudade de casa
feelings|shyness|كسوف|خجل|timidité|Schüchternheit|timidez|timidezza|timidez
feelings|acceptance|تقبّل|تقبل|acceptation|Akzeptanz|aceptación|accettazione|aceitação
actions|befriend|صاحب|صادق|se lier d'amitié|sich anfreunden|entablar amistad|fare amicizia|fazer amizade
concepts|neighborliness|حسن الجيرة|حسن الجوار|bon voisinage|gute Nachbarschaft|buena vecindad|buon vicinato|boa vizinhança
concepts|new beginning|بداية جديدة|بداية جديدة|nouveau départ|Neuanfang|nuevo comienzo|nuovo inizio|novo começo`},
        {name: 'Resolving a disagreement', entries: `concepts|misunderstanding|سوء تفاهم|سوء تفاهم|malentendu|Missverständnis|malentendido|malinteso|mal-entendido
concepts|assumption|افتراض|افتراض|supposition|Annahme|suposición|supposizione|suposição
concepts|point of view|وجهة نظر|وجهة نظر|point de vue|Standpunkt|punto de vista|punto di vista|ponto de vista
actions|interrupt|قاطع الكلام|قاطع الحديث|interrompre|unterbrechen|interrumpir|interrompere|interromper
actions|clarify|وضّح|وضّح|clarifier|klarstellen|aclarar|chiarire|esclarecer
feelings|frustration|إحباط|إحباط|frustration|Frustration|frustración|frustrazione|frustração
concepts|compromise|حل وسط|حل وسط|compromis|Kompromiss|acuerdo intermedio|compromesso|meio-termo
concepts|apology|اعتذار|اعتذار|excuses|Entschuldigung|disculpa|scuse|pedido de desculpas
concepts|reconciliation|صلح|مصالحة|réconciliation|Versöhnung|reconciliación|riconciliazione|reconciliação
actions|acknowledge|اعترف بالحقيقة|اعترف|reconnaître|anerkennen|reconocer|riconoscere|reconhecer
actions|forgive|سامح|سامح|pardonner|verzeihen|perdonar|perdonare|perdoar
feelings|remorse|ندم|ندم|remords|Reue|remordimiento|rimorso|remorso
concepts|empathy|تعاطف|تعاطف|empathie|Empathie|empatía|empatia|empatia
concepts|boundary|حدود شخصية|حدود شخصية|limite personnelle|persönliche Grenze|límite personal|limite personale|limite pessoal
concepts|fairness|عدل بين الناس|إنصاف|équité|Fairness|equidad|equità|equidade
actions|negotiate|اتفاوض|تفاوض|négocier|verhandeln|negociar|negoziare|negociar
actions|listen carefully|اسمع كويس|أنصت باهتمام|écouter attentivement|aufmerksam zuhören|escuchar con atención|ascoltare attentamente|ouvir com atenção
feelings|relief after a quarrel|راحة بعد خناقة|ارتياح بعد خلاف|soulagement après dispute|Erleichterung nach Streit|alivio tras una discusión|sollievo dopo un litigio|alívio após uma discussão`},
        {name: 'Helping others', entries: `people|mentor|مرشد|مرشد|mentor|Mentor|mentor|mentore|mentor
people|apprentice|صبي في الورشة|متدرّب مهني|apprenti|Lehrling|aprendiz|apprendista|aprendiz
people|caregiver|مرافق مريض|مقدّم رعاية|aidant|Betreuungsperson|cuidador|assistente alla persona|cuidador
work|charity|جمعية خيرية|جمعية خيرية|association caritative|Wohltätigkeitsverein|organización benéfica|associazione benefica|instituição de caridade
work|food bank|بنك طعام|بنك طعام|banque alimentaire|Lebensmittelbank|banco de alimentos|banco alimentare|banco de alimentos
work|community kitchen|مطبخ خيري|مطبخ خيري|cantine solidaire|Suppenküche|comedor social|mensa solidale|cozinha comunitária
actions|guide|دلّ|أرشد|guider|anleiten|guiar|guidare|orientar
actions|teach a skill|علّم صنعة|علّم مهارة|transmettre un savoir-faire|eine Fertigkeit vermitteln|enseñar una habilidad|insegnare un'abilità|ensinar uma habilidade
actions|offer a seat|سيب مكانك لحد|قدّم مقعدك|céder sa place|einen Sitz anbieten|ceder el asiento|cedere il posto|ceder o lugar
actions|share a meal|كلوا سوا|شارك الطعام|partager un repas|eine Mahlzeit teilen|compartir una comida|condividere un pasto|compartilhar uma refeição
actions|check on someone|اطمّن على حد|اطمئن على شخص|prendre des nouvelles|nach jemandem sehen|ver cómo está alguien|chiedere come sta qualcuno|ver como alguém está
concepts|small kindness|معروف صغير|معروف صغير|petite gentillesse|kleine Freundlichkeit|pequeña amabilidad|piccola gentilezza|pequena gentileza
concepts|solidarity|تضامن|تضامن|solidarité|Solidarität|solidaridad|solidarietà|solidariedade
concepts|dignity|كرامة|كرامة|dignité|Würde|dignidad|dignità|dignidade
concepts|reciprocity|رد المعروف|تبادل المعروف|réciprocité|Gegenseitigkeit|reciprocidad|reciprocità|reciprocidade
feelings|gratitude|امتنان|امتنان|reconnaissance|Dankbarkeit|gratitud|gratitudine|gratidão
feelings|appreciation|تقدير|تقدير|appréciation|Wertschätzung|aprecio|apprezzamento|apreço
concepts|lasting impact|أثر ما بيتنسيش|أثر دائم|effet durable|bleibende Wirkung|efecto duradero|impatto duraturo|impacto duradouro`},
        {name: 'Personal challenge', entries: `concepts|goal setting|تحديد هدف|تحديد هدف|fixation d'objectif|Zielsetzung|fijación de metas|definizione degli obiettivi|definição de metas
concepts|self-discipline|ضبط النفس|انضباط ذاتي|autodiscipline|Selbstdisziplin|autodisciplina|autodisciplina|autodisciplina
concepts|practice routine|نظام تمرين|تدريب منتظم|routine d'entraînement|Übungsroutine|rutina de práctica|routine di esercizio|rotina de prática
actions|persevere|ما تستسلمش|ثابر|persévérer|durchhalten|perseverar|perseverare|perseverar
actions|adapt|اتأقلم|تأقلم|s'adapter|sich anpassen|adaptarse|adattarsi|se adaptar
feelings|self-doubt|شك في نفسك|شك في النفس|doute de soi|Selbstzweifel|duda de sí mismo|insicurezza personale|dúvida de si
concepts|setback|خطوة لورا|انتكاسة|revers|Rückschlag|contratiempo|battuta d'arresto|revés
concepts|obstacle|عقبة|عقبة|obstacle|Hindernis|obstáculo|ostacolo|obstáculo
concepts|learning curve|اتعلّم واحدة واحدة|التدرّج في التعلّم|courbe d'apprentissage|Lernkurve|curva de aprendizaje|curva di apprendimento|curva de aprendizagem
actions|try again|جرّب تاني|حاول مجددا|réessayer|erneut versuchen|volver a intentar|riprovare|tentar novamente
actions|take a chance|جرّب حظك|غامر|tenter sa chance|eine Chance wagen|arriesgarse|tentare la fortuna|arriscar
feelings|resolve|إصرار|إصرار|résolution|Entschiedenheit|firmeza|risolutezza|firmeza
concepts|breakthrough|نقلة كبيرة|تقدّم كبير|percée|Durchbruch|avance decisivo|svolta|avanço decisivo
concepts|self-belief|ثقة في نفسك|ثقة بالنفس|confiance en soi|Selbstvertrauen|confianza en uno mismo|fiducia in sé|autoconfiança
concepts|resilience|تقوم على رجلك تاني|قدرة على التعافي|résilience|Widerstandskraft|resiliencia|resilienza|resiliência
actions|celebrate progress|افرح باللي حققته|احتفل بالتقدم|célébrer les progrès|Fortschritte feiern|celebrar avances|festeggiare i progressi|celebrar avanços
feelings|fulfilment|إحساس بالإنجاز|شعور بالإنجاز|épanouissement|Erfüllung|realización|realizzazione|realização
concepts|personal growth|تطوير نفسك|تطوير الذات|évolution personnelle|persönliche Entwicklung|crecimiento personal|crescita personale|crescimento pessoal`},
    ]},
    {name: 'Time and change', scenes: [
        {name: 'Daily rhythms', entries: `time|daybreak|طلوع النهار|بزوغ الفجر|point du jour|Tagesanbruch|alba|alba|alvorecer
time|early morning|بدري الصبح|الصباح الباكر|petit matin|früher Morgen|primera hora de la mañana|prima mattina|cedo pela manhã
time|midmorning|وقت الضحى|منتصف الصباح|milieu de matinée|Vormittagsmitte|media mañana|metà mattina|meio da manhã
time|midday|الضهر|الظهيرة|midi|Mittag|mediodía|mezzogiorno|meio-dia
time|late afternoon|آخر العصر|أواخر العصر|fin d'après-midi|später Nachmittag|última hora de la tarde|tardo pomeriggio|fim da tarde
time|dusk|أول المغرب|الغسق|crépuscule|Abenddämmerung|crepúsculo|crepuscolo|crepúsculo
time|nighttime|وقت الليل|وقت الليل|nuit|Nachtzeit|horario nocturno|notte|período noturno
time|small hours|آخر الليل|آخر الليل|heures après minuit|Stunden nach Mitternacht|madrugada|ore piccole|madrugada
time|weekday|يوم شغل|يوم عمل|jour de semaine|Wochentag|día laborable|giorno feriale|dia útil
time|day off|يوم إجازة|يوم عطلة|jour de repos|freier Tag|día libre|giorno libero|dia de folga
time|overnight|طول الليل|طوال الليل|pendant la nuit|über Nacht|durante la noche|durante la notte|durante a noite
time|all day long|طول اليوم|طوال اليوم|toute la journée|den ganzen Tag|todo el día|tutto il giorno|o dia inteiro
actions|wake up|اصحى|استيقظ|se réveiller|aufwachen|despertarse|svegliarsi|acordar
actions|get ready|جهّز نفسك|استعد|se préparer|sich fertig machen|prepararse|prepararsi|se preparar
actions|wind down|ريّح شوية|استرخِ|se détendre|zur Ruhe kommen|relajarse|rilassarsi|desacelerar
concepts|body clock|الساعة البيولوجية|الساعة البيولوجية|horloge biologique|innere Uhr|reloj biológico|orologio biologico|relógio biológico
feelings|alertness|صاحي وفايق|يقظة|vigilance|Wachheit|estado de alerta|vigilanza|estado de alerta
feelings|fatigue|إرهاق|إرهاق|fatigue|Erschöpfung|fatiga|affaticamento|fadiga`},
        {name: 'Calendar', entries: `time|fortnight|أسبوعين|أسبوعان|quinzaine|vierzehn Tage|quincena|quindicina|quinzena
time|quarter year|ربع سنة|ربع سنة|trimestre|Quartal|trimestre|trimestre|trimestre
time|half year|نص سنة|نصف سنة|semestre|Halbjahr|semestre|semestre|semestre
time|leap year|سنة كبيسة|سنة كبيسة|année bissextile|Schaltjahr|año bisiesto|anno bisestile|ano bissexto
time|millennium|ألف سنة|ألفية|millénaire|Jahrtausend|milenio|millennio|milênio
time|era|عصر|حقبة|ère|Zeitalter|era|era|era
time|February|فبراير|فبراير|février|Februar|febrero|febbraio|fevereiro
time|April|أبريل|أبريل|avril|April|abril|aprile|abril
time|June|يونيو|يونيو|juin|Juni|junio|giugno|junho
time|August|أغسطس|أغسطس|août|August|agosto|agosto|agosto
time|October|أكتوبر|أكتوبر|octobre|Oktober|octubre|ottobre|outubro
time|December|ديسمبر|ديسمبر|décembre|Dezember|diciembre|dicembre|dezembro
time|New Year's Eve|ليلة راس السنة|ليلة رأس السنة|réveillon du Nouvel An|Silvester|nochevieja|vigilia di Capodanno|véspera de Ano-Novo
time|wedding anniversary|عيد جواز|ذكرى زواج|anniversaire de mariage|Hochzeitstag|aniversario de boda|anniversario di matrimonio|aniversário de casamento
time|school holiday|إجازة المدارس|عطلة مدرسية|vacances scolaires|Schulferien|vacaciones escolares|vacanze scolastiche|férias escolares
time|bank holiday|إجازة رسمية|عطلة رسمية|jour férié|Feiertag|día festivo|giorno festivo|feriado
objects|wall calendar|نتيجة الحيطة|تقويم الحائط|calendrier mural|Wandkalender|calendario de pared|calendario da parete|calendário de parede
actions|mark a date|علّم على يوم|علّم يومًا في التقويم|marquer une date|ein Datum markieren|marcar una fecha|segnare una data|marcar uma data`},
        {name: 'Planning', entries: `work|schedule|جدول يومي|جدول يومي|emploi du temps|Zeitplan|programación|programma giornaliero|programação diária
work|deadline extension|مدّ الميعاد|تمديد المهلة|prolongation de délai|Fristverlängerung|prórroga|proroga|prorrogação
work|reminder|تذكير|تذكير|rappel de tâche|Erinnerungsnotiz|recordatorio|promemoria|lembrete
objects|stopwatch|ساعة إيقاف|ساعة إيقاف|chronomètre|Stoppuhr|cronómetro|cronometro|cronômetro
technology|timer|تايمر|مؤقت|minuteur|Zeitmesser|temporizador|timer|temporizador
concepts|priority|أولوية|أولوية|priorité|Priorität|prioridad|priorità|prioridade
time|beforehand|قبلها|سلفا|à l'avance|vorher|de antemano|in anticipo|com antecedência
time|afterwards|بعدها|بعد ذلك|ensuite|danach|después|dopo|depois
time|meanwhile|في الوقت ده|في هذه الأثناء|pendant ce temps|inzwischen|mientras tanto|nel frattempo|enquanto isso
time|simultaneously|في نفس اللحظة|في وقت واحد|simultanément|gleichzeitig|simultáneamente|simultaneamente|simultaneamente
time|punctually|في الميعاد|في الموعد|ponctuellement|pünktlich|puntualmente|puntualmente|pontualmente
time|eventually|في الآخر|في نهاية المطاف|finalement|schließlich|finalmente|alla fine|por fim
actions|postpone|أجّل|أجّل|reporter|verschieben|posponer|rimandare|adiar
actions|reschedule|غيّر الميعاد|غيّر الموعد|reprogrammer|neu terminieren|reprogramar|riprogrammare|reagendar
actions|catch up|عوّض اللي فات|عوّض ما فات|rattraper|aufholen|ponerse al día|recuperare|colocar em dia
concepts|time management|تنظيم الوقت|إدارة الوقت|gestion du temps|Zeitmanagement|gestión del tiempo|gestione del tempo|gestão do tempo
feelings|time pressure|ضغط الوقت|ضغط الوقت|pression du temps|Zeitdruck|presión de tiempo|pressione del tempo|pressão de tempo
concepts|breathing space|فرصة للراحة|فسحة للتفكير|répit|Verschnaufpause|respiro|spazio di respiro|folga para respirar`},
        {name: 'Memory', entries: `objects|diary|مذكرات|يوميات|journal intime|Tagebuch|diario personal|diario personale|diário pessoal
objects|scrapbook|دفتر ذكريات|دفتر قصاصات|album de souvenirs|Erinnerungsalbum|álbum de recortes|album dei ricordi|álbum de recortes
objects|time capsule|كبسولة زمنية|كبسولة زمنية|capsule temporelle|Zeitkapsel|cápsula del tiempo|capsula del tempo|cápsula do tempo
objects|old photograph|صورة قديمة|صورة قديمة|vieille photo|altes Foto|foto antigua|vecchia fotografia|fotografia antiga
objects|faded letter|جواب باهت|رسالة باهتة|lettre décolorée|verblasster Brief|carta descolorida|lettera sbiadita|carta desbotada
objects|childhood toy|لعبة الطفولة|لعبة طفولة|jouet d'enfance|Kindheitsspielzeug|juguete de infancia|giocattolo d'infanzia|brinquedo de infância
time|childhood|طفولة|طفولة|enfance|Kindheit|infancia|infanzia|infância
time|adolescence|مراهقة|مراهقة|adolescence|Jugendzeit|adolescencia|adolescenza|adolescência
time|bygone days|أيام زمان|أيام مضت|jours d'autrefois|vergangene Tage|tiempos pasados|tempi passati|tempos passados
time|the distant past|زمان قوي|الماضي البعيد|passé lointain|ferne Vergangenheit|pasado remoto|passato lontano|passado distante
concepts|recollection|ذكرى قديمة|استرجاع ذكرى|réminiscence|Erinnerung|rememoración|reminiscenza|recordação
concepts|oral history|حكايات من زمان|تاريخ شفوي|histoire orale|mündliche Geschichte|historia oral|storia orale|história oral
actions|reminisce|افتكر زمان|استرجع الذكريات|se remémorer|in Erinnerungen schwelgen|rememorar|rievocare|recordar
actions|recognize|اتعرّف عليه|تعرّف عليه|reconnaître une personne|wiedererkennen|identificar|riconoscere una persona|reconhecer alguém
actions|misplace|ضيّع حاجة|ضعه في غير مكانه|égarer|verlegen|extraviar|smarrire|pôr no lugar errado
feelings|nostalgia|حنين لأيام زمان|حنين للماضي|nostalgie|Nostalgie|nostalgia|nostalgia|nostalgia
feelings|bittersweetness|فرحة وحزن سوا|فرح ممزوج بحزن|joie mêlée de tristesse|Freude mit Wehmut|alegría agridulce|gioia mista a tristezza|alegria agridoce
concepts|legacy|اللي سابه وراه|إرث|héritage|Vermächtnis|legado|eredità|legado`},
        {name: 'Seasonal change', entries: `nature|bud|برعم|برعم|bourgeon|Knospe|brote|germoglio|broto
nature|pollen|حبوب لقاح|حبوب لقاح|pollen|Pollen|polen|polline|pólen
nature|sap|عصارة شجر|نسغ|sève|Baumsaft|savia|linfa|seiva
nature|thaw|دوبان التلج|ذوبان الجليد|dégel|Tauwetter|deshielo|disgelo|degelo
actions|sprout|ينبت|ينبت|germer|sprießen|brotar|germogliare|brotar
time|spring equinox|اعتدال ربيعي|اعتدال ربيعي|équinoxe de printemps|Frühlingsäquinoktium|equinoccio de primavera|equinozio di primavera|equinócio de primavera
nature|heat haze|تموّج الهوا|تموّج الهواء الحار|brume de chaleur|Hitzeflimmern|reverberación del calor|foschia da calore|névoa de calor
nature|dry season|موسم جفاف|موسم جفاف|saison sèche|Trockenzeit|estación seca|stagione secca|estação seca
nature|monsoon|مطر موسمي|أمطار موسمية|mousson|Monsun|monzón|monsone|monção
time|summer solstice|انقلاب صيفي|انقلاب صيفي|solstice d'été|Sommersonnenwende|solsticio de verano|solstizio d'estate|solstício de verão
actions|ripen|الفاكهة تستوي|نضج الثمار|mûrir|reifen|madurar|maturare|amadurecer
concepts|abundance|وفرة|وفرة|abondance|Überfluss|abundancia|abbondanza|abundância
nature|fallen leaves|ورق الشجر الواقع|أوراق متساقطة|feuilles mortes|Laub|hojas caídas|foglie cadute|folhas caídas
nature|snowdrift|كومة تلج|كومة ثلج|congère|Schneewehe|ventisquero|cumulo di neve|monte de neve
animals|hibernation|بيات شتوي|سبات شتوي|hibernation|Winterschlaf|hibernación|letargo|hibernação
time|winter solstice|انقلاب شتوي|انقلاب شتوي|solstice d'hiver|Wintersonnenwende|solsticio de invierno|solstizio d'inverno|solstício de inverno
concepts|renewal|حياة جديدة|تجدد|renouveau|Erneuerung|renovación|rinnovamento|renovação
concepts|natural cycle|دورة طبيعية|دورة طبيعية|cycle naturel|Naturkreislauf|ciclo natural|ciclo naturale|ciclo natural`},
        {name: 'Looking ahead', entries: `time|next week|الأسبوع الجاي|الأسبوع القادم|semaine prochaine|nächste Woche|la próxima semana|la prossima settimana|na próxima semana
time|next month|الشهر الجاي|الشهر القادم|mois prochain|nächsten Monat|el próximo mes|il mese prossimo|no próximo mês
time|in a year|كمان سنة|بعد عام|dans un an|in einem Jahr|dentro de un año|tra un anno|daqui a um ano
time|sooner or later|مسألة وقت|عاجلا أم آجلا|tôt ou tard|früher oder später|tarde o temprano|prima o poi|mais cedo ou mais tarde
time|for the time being|مؤقتًا|في الوقت الراهن|pour le moment|vorerst|por ahora|per il momento|por enquanto
time|in the long run|على المدى البعيد|على المدى البعيد|à long terme|auf lange Sicht|a largo plazo|a lungo termine|a longo prazo
concepts|possibility|احتمال|إمكانية|possibilité|Möglichkeit|posibilidad|possibilità|possibilidade
concepts|opportunity|فرصة|فرصة|occasion favorable|Gelegenheit|oportunidad|opportunità|oportunidade
concepts|intention|نية|نية|intention|Absicht|intención|intenzione|intenção
concepts|aspiration|طموح|طموح|aspiration|Bestreben|aspiración|aspirazione|aspiração
concepts|foresight|بعد نظر|بعد نظر|prévoyance|Weitsicht|previsión|lungimiranza|previsão
concepts|uncertainty|مش متأكد|عدم يقين|incertitude|Ungewissheit|incertidumbre|incertezza|incerteza
actions|envisage|تخيّل|تصوّر|envisager|sich vorstellen|imaginar un futuro|immaginare un futuro|imaginar um futuro
actions|prepare|حضّر|حضّر|préparer|vorbereiten|preparar|preparare|preparar
actions|commit|التزم|التزم|s'engager|sich verpflichten|comprometerse|impegnarsi|se comprometer
feelings|optimism|تفاؤل|تفاؤل|optimisme|Optimismus|optimismo|ottimismo|otimismo
feelings|apprehension|قلق من اللي جاي|توجس|appréhension|Beklommenheit|aprensión|apprensione|apreensão
concepts|potential|إمكانيات مستخبية|إمكانات كامنة|potentiel|Potenzial|potencial|potenziale|potencial`},
    ]},
    {name: 'Body and play', scenes: [
        {name: 'The senses', entries: `concepts|eyesight|نظر|بصر|vue|Sehkraft|vista|vista|visão
concepts|hearing|سمع|سمع|ouïe|Gehör|oído|udito|audição
concepts|sense of smell|حاسة الشم|حاسة الشم|odorat|Geruchssinn|olfato|olfatto|olfato
concepts|sense of taste|حاسة التذوق|حاسة التذوق|goût|Geschmackssinn|gusto|gusto|paladar
concepts|sense of touch|حاسة اللمس|حاسة اللمس|toucher|Tastsinn|tacto|tatto|tato
concepts|sense of balance|حاسة التوازن|حاسة التوازن|sens de l'équilibre|Gleichgewichtssinn|sentido del equilibrio|senso dell'equilibrio|sentido de equilíbrio
objects|eyelash|رمش|رمش|cil|Wimper|pestaña del ojo|ciglio|cílio
objects|eyebrow|حاجب|حاجب|sourcil|Augenbraue|ceja|sopracciglio|sobrancelha
objects|earlobe|شحمة ودن|شحمة أذن|lobe de l'oreille|Ohrläppchen|lóbulo|lobo dell'orecchio|lóbulo da orelha
objects|nostril|فتحة مناخير|فتحة الأنف|narine|Nasenloch|fosa nasal|narice|narina
objects|fingertip|طرف صباع|طرف إصبع|bout du doigt|Fingerspitze|yema del dedo|polpastrello|ponta do dedo
objects|palm|كف|كف|paume|Handfläche|palma de la mano|palmo|palma da mão
actions|glance|بصّة سريعة|ألقِ نظرة|jeter un coup d'œil|einen Blick werfen|echar un vistazo|dare un'occhiata|dar uma olhada
actions|sniff|شمّ|شُمّ|renifler|schnuppern|olfatear|annusare|farejar
actions|squint|ضيّق عينيك|ضيّق عينيك|plisser les yeux|die Augen zusammenkneifen|entrecerrar los ojos|socchiudere gli occhi|semicerrar os olhos
actions|whisper|وشوش|اهمس|chuchoter|flüstern|susurrar|sussurrare|sussurrar
feelings|ticklishness|زغزغة|دغدغة|chatouillement|Kitzeligkeit|cosquillas|solletico|cócegas
feelings|tingling|تنميل|وخز|picotement|Kribbeln|hormigueo|formicolio|formigamento`},
        {name: 'Movement', entries: `objects|shoulder|كتف|كتف|épaule|Schulter|hombro|spalla|ombro
objects|elbow|كوع|مرفق|coude|Ellenbogen|codo|gomito|cotovelo
objects|wrist|مفصل الإيد|رسغ|poignet|Handgelenk|muñeca|polso|pulso
objects|ankle|كاحل الرجل|كاحل|cheville du pied|Knöchel|tobillo|caviglia|tornozelo
objects|heel|كعب رجل|عَقِب القدم|talon|Ferse|talón|tallone|calcanhar
objects|knee|ركبة|ركبة|genou|Knie|rodilla|ginocchio|joelho
actions|bend|اتني|اثنِ|plier le corps|beugen|doblar el cuerpo|piegarsi|dobrar o corpo
actions|twist|لف جسمك|لُفّ جسمك|se tordre|sich drehen|girar el cuerpo|torcersi|girar o corpo
actions|leap|انط بعيد|اقفز بعيدا|bondir|springen|saltar lejos|balzare|saltar longe
actions|tiptoe|امشي على صوابعك|امشِ على أطراف أصابعك|marcher sur la pointe des pieds|auf Zehenspitzen gehen|andar de puntillas|camminare in punta di piedi|andar na ponta dos pés
actions|crouch|قرفص|قرفِص|s'accroupir|sich hinhocken|agacharse|accovacciarsi|agachar
actions|balance|اتوازن|توازن|se tenir en équilibre|balancieren|mantener el equilibrio|stare in equilibrio|se equilibrar
concepts|flexibility|مرونة الجسم|ليونة|souplesse|Beweglichkeit|flexibilidad|flessibilità|flexibilidade
concepts|coordination|تناسق الحركة|تناسق حركي|coordination|Koordination|coordinación|coordinazione|coordenação
concepts|posture|وضع الجسم|وضعية الجسم|posture|Körperhaltung|postura|postura|postura
concepts|stamina|قدرة تحمّل|قوة تحمل|résistance physique|Kondition|aguante|resistenza fisica|fôlego
time|warm-up|تسخين|إحماء|échauffement|Aufwärmen|calentamiento|riscaldamento|aquecimento
time|cool-down|تهدئة بعد التمرين|تمارين تهدئة|retour au calme|Abkühlen|vuelta a la calma|defaticamento|desaquecimento`},
        {name: 'Ball games', entries: `people|goalkeeper|حارس مرمى|حارس مرمى|gardien de but|Torwart|portero|portiere di calcio|goleiro
people|referee|حكم|حكم|arbitre|Schiedsrichter|árbitro|arbitro|árbitro
people|spectator|متفرج|متفرج|spectateur|Zuschauer|espectador|spettatore|espectador
objects|goalpost|قائم الجون|قائم مرمى|poteau de but|Torpfosten|poste de portería|palo della porta|trave do gol
objects|corner flag|راية الكورنر|راية ركنية|drapeau de corner|Eckfahne|banderín de córner|bandierina|bandeirinha de escanteio
concepts|kickoff|ضربة البداية|ركلة البداية|coup d'envoi|Anstoß|saque inicial|calcio d'inizio|pontapé inicial
concepts|penalty kick|ضربة جزاء|ركلة جزاء|penalty|Elfmeter|penalti|calcio di rigore|pênalti
concepts|offside|تسلل|تسلل|hors-jeu|Abseits|fuera de juego|fuorigioco|impedimento
work|substitution|تبديل لاعب|تبديل لاعب|remplacement|Spielerwechsel|sustitución|sostituzione|substituição
actions|dribble|راوغ|راوغ|dribbler|dribbeln|regatear|dribblare|driblar
actions|pass the ball|باصي|مرّر الكرة|passer le ballon|den Ball passen|pasar el balón|passare la palla|passar a bola
actions|tackle|اقطع الكورة|انتزع الكرة|tacler|den Ball erobern|hacer una entrada|contrastare|desarmar
objects|basketball hoop|حلقة الباسكت|حلقة السلة|panier de basket|Basketballkorb|aro de baloncesto|canestro|cesta de basquete
objects|tennis racket|مضرب تنس|مضرب تنس|raquette de tennis|Tennisschläger|raqueta de tenis|racchetta da tennis|raquete de tênis
objects|shuttlecock|ريشة البادمنتون|ريشة كرة الريشة|volant de badminton|Federball|volante de bádminton|volano|peteca
concepts|rally in sport|تبادل ضربات|تبادل ضربات|échange sportif|Ballwechsel|peloteo|scambio|troca de bolas
feelings|sportsmanship|روح رياضية|روح رياضية|esprit sportif|Sportlichkeit|deportividad|sportività|espírito esportivo
time|extra time|وقت إضافي|وقت إضافي|prolongation|Verlängerung|prórroga deportiva|tempi supplementari|prorrogação esportiva`},
        {name: 'Athletics', entries: `people|sprinter|عداء سرعة|عداء سرعة|sprinteur|Sprinter|velocista|velocista|velocista
people|marathon runner|عداء ماراثون|عداء ماراثون|marathonien|Marathonläufer|maratonista|maratoneta|maratonista
people|coach|مدرّب|مدرب|entraîneur|Trainer|entrenador|allenatore|treinador
objects|starting block|مكعب بداية|مكعب انطلاق|starting-block|Startblock|taco de salida|blocco di partenza|bloco de partida
objects|baton|عصاية تتابع|عصا تتابع|témoin de relais|Staffelstab|testigo de relevos|testimone|bastão de revezamento
objects|hurdle|حاجز سباق|حاجز سباق|haie|Hürde|valla de atletismo|ostacolo atletico|barreira de atletismo
places|running track|مضمار جري|مضمار جري|piste d'athlétisme|Laufbahn|pista de atletismo|pista di atletica|pista de atletismo
objects|finish line|خط النهاية|خط نهاية|ligne d'arrivée|Ziellinie|línea de meta|traguardo di corsa|linha de chegada
objects|medal|ميدالية|ميدالية|médaille|Medaille|medalla|medaglia|medalha
work|qualifying round|تصفيات|جولة تأهيلية|éliminatoires|Qualifikationsrunde|clasificatoria|qualificazione|classificatória
concepts|personal best|أحسن رقم ليك|أفضل رقم شخصي|record personnel|persönliche Bestleistung|marca personal|record personale|recorde pessoal
actions|pace yourself|وزّع مجهودك|وزّع جهدك|gérer son effort|Kräfte einteilen|regular el esfuerzo|dosare le forze|dosar o esforço
objects|gymnastics mat|مرتبة جمباز|بساط جمباز|tapis de gymnastique|Turnmatte|colchoneta|tappetino da ginnastica|colchonete
objects|balance beam|عارضة توازن|عارضة توازن|poutre de gymnastique|Schwebebalken|barra de equilibrio|trave di equilibrio|trave de equilíbrio
objects|vaulting pole|زانة|زانة|perche|Sprungstab|pértiga|asta|vara de salto
actions|somersault|شقلبة|تشقلب|faire un saut périlleux|einen Salto machen|dar una voltereta|fare una capriola|dar uma cambalhota
feelings|competitive spirit|روح المنافسة|روح المنافسة|esprit de compétition|Wettkampfgeist|espíritu competitivo|spirito competitivo|espírito competitivo
concepts|team spirit|روح الفريق|روح الفريق|esprit d'équipe|Teamgeist|espíritu de equipo|spirito di squadra|espírito de equipe`},
        {name: 'Board games', entries: `objects|chessboard|رقعة شطرنج|رقعة شطرنج|échiquier|Schachbrett|tablero de ajedrez|scacchiera|tabuleiro de xadrez
objects|pawn|عسكري شطرنج|بيدق|pion|Bauer im Schach|peón|pedone|peão
objects|rook|طابية|رخ|tour d'échecs|Turm im Schach|torre de ajedrez|torre degli scacchi|torre de xadrez
objects|bishop|فيل شطرنج|فيل شطرنج|fou d'échecs|Läufer im Schach|alfil|alfiere|bispo de xadrez
objects|chess knight|حصان شطرنج|حصان شطرنج|cavalier d'échecs|Springer im Schach|caballo de ajedrez|cavallo degli scacchi|cavalo de xadrez
concepts|checkmate|كش مات|كش مات|échec et mat|Schachmatt|jaque mate|scacco matto|xeque-mate
objects|dice|زهر|نرد|dés|Würfel|dados|dadi|dados
objects|domino|دومينو|دومينو|domino|Domino|dominó|domino|dominó
objects|playing card|ورقة كوتشينة|ورقة لعب|carte à jouer|Spielkarte|carta de la baraja|carta da gioco|carta de baralho
objects|game token|حجر اللعبة|قطعة لعب|jeton|Spielfigur|ficha de juego|pedina|peça de jogo
concepts|turn order|ترتيب الأدوار|ترتيب الأدوار|ordre de jeu|Zugreihenfolge|orden de turnos|ordine dei turni|ordem das jogadas
actions|shuffle|اخلط الورق|اخلط الأوراق|mélanger les cartes|Karten mischen|barajar|mescolare le carte|embaralhar
concepts|strategy|خطة لعب|استراتيجية|stratégie|Strategie|estrategia|strategia|estratégia
concepts|bluff|خدعة في اللعب|تمويه|bluff|Bluff|farol|bluff|blefe
concepts|stalemate|تعادل بالبات|تعادل بالبات|pat|Patt|ahogado|stallo|afogamento no xadrez
actions|take turns|كل واحد بدوره|تبادلوا الأدوار|jouer à tour de rôle|abwechseln|turnarse|fare a turno|se revezar
feelings|playfulness|هزار ولعب|مرح|espièglerie|Verspieltheit|jovialidad|giocosità|espírito brincalhão
concepts|fair play|لعب نضيف|لعب نزيه|jeu loyal|faires Spiel|juego limpio|gioco leale|jogo limpo`},
        {name: 'Puzzle room', entries: `objects|jigsaw piece|قطعة بازل|قطعة أحجية|pièce de puzzle|Puzzleteil|pieza de puzle|pezzo di puzzle|peça de quebra-cabeça
objects|padlock|قفل معلق|قفل معلق|cadenas|Vorhängeschloss|candado|lucchetto|cadeado
objects|combination dial|قرص أرقام|قرص أرقام|cadran de combinaison|Zahlenscheibe|dial de combinación|quadrante a combinazione|disco de combinação
objects|hidden compartment|درج سري|حجرة سرية|compartiment secret|Geheimfach|compartimento secreto|scomparto segreto|compartimento secreto
objects|cipher wheel|قرص الشفرة|قرص تشفير|roue de chiffrement|Chiffrierscheibe|rueda de cifrado|ruota cifrante|roda de cifra
technology|ultraviolet lamp|كشاف أشعة فوق بنفسجية|مصباح أشعة فوق بنفسجية|lampe ultraviolette|UV-Lampe|lámpara ultravioleta|lampada ultravioletta|lâmpada ultravioleta
concepts|pattern recognition|تمييز أنماط|التعرّف على الأنماط|reconnaissance de motifs|Mustererkennung|reconocimiento de patrones|riconoscimento di schemi|reconhecimento de padrões
concepts|logic|منطق|منطق|logique|Logik|lógica|logica|lógica
concepts|sequence|تسلسل|تسلسل|séquence|Abfolge|secuencia|sequenza|sequência
concepts|combination|رقم القفل|رمز القفل|combinaison|Kombination|combinación|combinazione|combinação
concepts|hidden message|رسالة مخفية|رسالة مخفية|message caché|versteckte Nachricht|mensaje oculto|messaggio nascosto|mensagem oculta
actions|decode|فك الشفرة|فك الشفرة|décoder|entschlüsseln|descifrar|decifrare|decifrar
actions|rotate|دوّر|أدر|faire pivoter|drehen|rotar|ruotare|girar
actions|align|خلّيهم على خط واحد|حاذِ القطع|aligner|ausrichten|alinear|allineare|alinhar
actions|connect clues|اربط الأدلة|اربط الأدلة|relier les indices|Hinweise verbinden|conectar pistas|collegare gli indizi|conectar pistas
feelings|suspense|تشويق|تشويق|suspense|Spannung|suspense|suspense|suspense
feelings|eureka moment|فهمتها!|وجدتها!|déclic|Aha-Erlebnis|momento de revelación|momento di illuminazione|momento de descoberta
time|last second|آخر ثانية|الثانية الأخيرة|dernière seconde|letzte Sekunde|último segundo|ultimo secondo|último segundo`},
    ]},
    {name: 'Everyday city life', scenes: [
        {name: 'Clothing', entries: `objects|waistcoat|صديري|صديري|gilet|Weste|chaleco|panciotto|colete
objects|cardigan|كارديجان|سترة صوفية مفتوحة|cardigan|Strickjacke|cárdigan|cardigan|cardigã
objects|raincoat|بالطو مطر|معطف مطر|imperméable|Regenmantel|impermeable|impermeabile|capa de chuva
objects|tracksuit|تريننج|بدلة رياضية|survêtement|Trainingsanzug|chándal|tuta sportiva|agasalho
objects|dungarees|سالوبت|بنطال بحمالات|salopette|Latzhose|peto|salopette|jardineira
objects|apron|مريلة مطبخ|مئزر|tablier|Schürze|delantal|grembiule|avental
objects|cuff|إسورة الكم|حافة الكم|manchette|Manschette|puño de manga|polsino|punho da manga
objects|collar|ياقة|ياقة|col|Kragen|cuello de camisa|colletto|colarinho
objects|hem|ديل الهدوم|حاشية ثوب|ourlet|Saum|dobladillo|orlo|bainha
objects|pocket|جيب|جيب|poche|Tasche am Kleid|bolsillo|tasca|bolso de roupa
objects|shoelace|رباط جزمة|رباط حذاء|lacet|Schnürsenkel|cordón de zapato|laccio|cadarço
objects|buckle|توكة حزام|إبزيم|boucle de ceinture|Schnalle|hebilla|fibbia|fivela
objects|beret|بيريه|قبعة بيريه|béret|Baskenmütze|boina|basco|boina
objects|beanie|طاقية صوف|قبعة صوفية|bonnet|Wollmütze|gorro de lana|berretto di lana|gorro de lã
objects|mittens|جوانتي بصباع واحد|قفازات بإبهام منفصل|moufles|Fäustlinge|manoplas|muffole|luvas sem dedos separados
actions|try on|قيس الهدوم|جرّب الملابس|essayer un vêtement|anprobieren|probarse ropa|provare un vestito|experimentar roupa
concepts|dress code|قواعد اللبس|قواعد اللباس|code vestimentaire|Kleiderordnung|código de vestimenta|codice di abbigliamento|código de vestimenta
concepts|personal style|ستايلك|أسلوب اللباس|style personnel|persönlicher Stil|estilo personal|stile personale|estilo pessoal`},
        {name: 'Hairdresser', entries: `people|barber|حلاق|حلاق|barbier|Barbier|barbero|barbiere|barbeiro
people|hairdresser|كوافير|مصفف شعر|coiffeur|Friseur|peluquero|parrucchiere|cabeleireiro
objects|comb|مشط|مشط|peigne|Kamm|peine|pettine|pente
objects|hairbrush|فرشة شعر|فرشاة شعر|brosse à cheveux|Haarbürste|cepillo de pelo|spazzola per capelli|escova de cabelo
objects|hair clip|بنسة شعر|مشبك شعر|barrette|Haarspange|pinza de pelo|fermaglio|presilha
objects|hairband|توكة شعر|ربطة شعر|élastique à cheveux|Haargummi|coletero|elastico per capelli|elástico de cabelo
technology|hairdryer|سيشوار|مجفف شعر|sèche-cheveux|Haartrockner|secador|asciugacapelli|secador de cabelo
technology|hair clippers|ماكينة حلاقة|آلة قص شعر|tondeuse|Haarschneidemaschine|maquinilla|tagliacapelli|máquina de cortar cabelo
objects|razor|موس حلاقة|شفرة حلاقة|rasoir|Rasiermesser|navaja de afeitar|rasoio|navalha
objects|shaving foam|فوم حلاقة|رغوة حلاقة|mousse à raser|Rasierschaum|espuma de afeitar|schiuma da barba|espuma de barbear
objects|hand mirror|مراية بإيد|مرآة يد|miroir à main|Handspiegel|espejo de mano|specchio a mano|espelho de mão
objects|hairdressing cape|فوطة الحلاق|رداء حلاقة|cape de coiffure|Friseurumhang|capa de peluquería|mantella da parrucchiere|capa de cabeleireiro
concepts|fringe|قُصّة|غرة شعر|frange|Ponyfrisur|flequillo|frangia|franja
concepts|ponytail|ديل حصان|تسريحة ذيل الحصان|queue-de-cheval|Pferdeschwanz|coleta|coda di cavallo|rabo de cavalo
concepts|braid|ضفيرة|ضفيرة|tresse|Zopf|trenza|treccia|trança
actions|trim|ظبّط الأطراف|شذّب الأطراف|couper les pointes|Spitzen schneiden|recortar las puntas|spuntare|aparar as pontas
actions|shave|احلق|احلق|raser|rasieren|afeitar|radere|barbear
feelings|fresh look|لوك جديد|مظهر جديد|nouveau look|neues Aussehen|nuevo aspecto|nuovo look|novo visual`},
        {name: 'Shopping errands', entries: `people|cashier|كاشير|أمين صندوق|caissier|Kassierer|cajero|cassiere|caixa
people|shop assistant|بياع محل|بائع متجر|vendeur|Verkäufer|dependiente|commesso|vendedor
places|fitting room|أوضة البروفة|غرفة قياس|cabine d'essayage|Umkleidekabine|probador|camerino|provador
objects|shopping trolley|عربية السوبرماركت|عربة تسوق|chariot|Einkaufswagen|carrito de compra|carrello|carrinho de compras
objects|price tag|تيكت السعر|بطاقة سعر|étiquette de prix|Preisschild|etiqueta de precio|cartellino|etiqueta de preço
technology|barcode|باركود|رمز شريطي|code-barres|Strichcode|código de barras|codice a barre|código de barras
objects|receipt|إيصال|إيصال|ticket de caisse|Kassenbon|tique de compra|scontrino|recibo
objects|wallet|محفظة|محفظة|portefeuille|Geldbeutel|cartera|portafoglio|carteira
objects|credit card|كارت ائتمان|بطاقة ائتمان|carte de crédit|Kreditkarte|tarjeta de crédito|carta di credito|cartão de crédito
objects|loyalty card|كارت نقط|بطاقة ولاء|carte de fidélité|Kundenkarte|tarjeta de fidelidad|carta fedeltà|cartão de fidelidade
work|discount|خصم|خصم|réduction|Rabatt|descuento|sconto|desconto
work|refund|رجّع الفلوس|استرداد المال|remboursement|Rückerstattung|reembolso|rimborso|reembolso
work|exchange|استبدال|استبدال|échange en magasin|Umtausch|cambio de un artículo|cambio merce|troca de produto
work|warranty|ضمان|ضمان|garantie|Garantie|garantía|garanzia|garantia
work|stockroom|مخزن محل|مخزن متجر|réserve du magasin|Lagerraum|almacén de tienda|magazzino del negozio|estoque da loja
actions|compare prices|قارن الأسعار|قارن الأسعار|comparer les prix|Preise vergleichen|comparar precios|confrontare i prezzi|comparar preços
actions|save up|حوّش|ادخر|économiser|sparen|ahorrar|risparmiare|economizar
concepts|value for money|يستاهل تمنه|جودة بسعر مناسب|rapport qualité-prix|Preis-Leistungs-Verhältnis|relación calidad-precio|rapporto qualità-prezzo|custo-benefício`},
        {name: 'Balcony garden', entries: `objects|flowerpot|قصيص|أصيص|pot de fleurs|Blumentopf|maceta|vaso per piante|vaso de plantas
objects|watering can|رشاش زرع|إبريق ري|arrosoir|Gießkanne|regadera|annaffiatoio|regador
objects|pruning shears|مقص تقليم|مقص تقليم|sécateur|Gartenschere|tijeras de podar|cesoie|tesoura de poda
objects|gardening gloves|جوانتي زرع|قفازات بستنة|gants de jardinage|Gartenhandschuhe|guantes de jardinería|guanti da giardino|luvas de jardinagem
nature|potting soil|تراب للزرع|تربة للأصص|terreau|Blumenerde|sustrato|terriccio|terra para vasos
objects|seed packet|كيس بذور|كيس بذور|sachet de graines|Samentüte|sobre de semillas|bustina di semi|pacote de sementes
nature|seedling|شتلة|شتلة|plantule|Setzling|plántula|piantina|muda
nature|cutting|عقلة نبات|عقلة نبات|bouture|Steckling|esqueje|talea|estaca de planta
nature|root ball|جذور بترابها|جذور بتربتها|motte|Wurzelballen|cepellón|zolla|torrão
objects|trellis|تعريشة|تعريشة|treillis|Rankgitter|enrejado|graticcio|treliça
objects|plant label|تيكت النبتة|بطاقة اسم النبات|étiquette de plante|Pflanzenschild|etiqueta de planta|etichetta per piante|etiqueta de planta
actions|repot|غيّر القصيص|انقل إلى أصيص جديد|rempoter|umtopfen|trasplantar de maceta|rinvasare|trocar de vaso
nature|geranium|جارونيا|إبرة الراعي|géranium|Geranie|geranio|geranio|gerânio
nature|jasmine|ياسمين|ياسمين|jasmin|Jasmin|jazmín|gelsomino|jasmim
nature|lavender|لافندر|خزامى|lavande|Lavendel|lavanda|lavanda|lavanda
actions|prune|قصّ الفروع|قلّم|tailler une plante|beschneiden|podar|potare|podar
actions|weed|شيل الحشايش|أزل الأعشاب الضارة|désherber|jäten|desherbar|sarchiare|capinar
concepts|urban greenery|خضرة وسط البيوت|خضرة المدن|verdure urbaine|Stadtgrün|vegetación urbana|verde urbano|verde urbano`},
        {name: 'Newsroom', entries: `people|reporter|مراسل|مراسل|reporter|Reporter|reportero|cronista|repórter
people|editor|محرر|محرر|rédacteur|Redakteur|redactor|redattore|redator
people|proofreader|مصحح لغوي|مصحح لغوي|correcteur|Korrektor|corrector de textos|correttore di bozze|revisor
people|newsreader|مذيع أخبار|مذيع أخبار|présentateur du journal|Nachrichtensprecher|presentador de noticias|conduttore del telegiornale|apresentador de notícias
work|headline|عنوان رئيسي|عنوان رئيسي|gros titre|Schlagzeile|titular|titolo principale|manchete
work|byline|اسم الكاتب|اسم الكاتب|signature de l'article|Autorenzeile|firma del artículo|firma dell'articolo|assinatura da matéria
work|interview transcript|نص المقابلة|نص مقابلة|transcription d'entretien|Interviewtranskript|transcripción de entrevista|trascrizione dell'intervista|transcrição de entrevista
work|press release|بيان صحفي|بيان صحفي|communiqué de presse|Pressemitteilung|comunicado de prensa|comunicato stampa|comunicado de imprensa
work|editorial|افتتاحية|افتتاحية|éditorial|Leitartikel|editorial periodístico|editoriale|editorial jornalístico
work|correction|تصحيح خبر|تصحيح خبر|rectificatif|Richtigstellung|rectificación|rettifica|retificação
concepts|reliable source|مصدر موثوق|مصدر موثوق|source fiable|verlässliche Quelle|fuente fiable|fonte attendibile|fonte confiável
actions|verify|اتأكد|تحقق|vérifier|überprüfen|verificar|verificare|verificar
technology|teleprompter|شاشة تلقين|ملقن إلكتروني|téléprompteur|Teleprompter|teleprónter|gobbo elettronico|teleprompter
technology|broadcast studio|استوديو الأخبار|استوديو بث|studio de diffusion|Sendestudio|estudio de emisión|studio televisivo|estúdio de transmissão
work|live coverage|تغطية مباشرة|تغطية مباشرة|couverture en direct|Live-Berichterstattung|cobertura en directo|cronaca in diretta|cobertura ao vivo
actions|publish|انشر|انشر|publier|veröffentlichen|publicar|pubblicare|publicar
concepts|accuracy|صحة المعلومات|صحة المعلومات|exactitude|Genauigkeit|exactitud|accuratezza|exatidão
concepts|public interest|مصلحة الناس|المصلحة العامة|intérêt public|öffentliches Interesse|interés público|interesse pubblico|interesse público`},
        {name: 'Recycling', entries: `objects|recycling bin|صندوق إعادة التدوير|حاوية إعادة التدوير|bac de recyclage|Recyclingtonne|contenedor de reciclaje|bidone del riciclo|lixeira de reciclagem
objects|glass bottle|إزازة إزاز|زجاجة زجاجية|bouteille en verre|Glasflasche|botella de vidrio|bottiglia di vetro|garrafa de vidro
objects|tin can|علبة صفيح|علبة معدنية|boîte métallique|Konservendose|lata|lattina|lata
objects|cardboard|كرتون|كرتون|carton|Pappe|cartón|cartone|papelão
objects|reusable bag|شنطة قماش|حقيبة قابلة لإعادة الاستخدام|sac réutilisable|Mehrwegbeutel|bolsa reutilizable|borsa riutilizzabile|sacola reutilizável
objects|refill bottle|إزازة بتتملي تاني|زجاجة قابلة لإعادة التعبئة|bouteille rechargeable|Nachfüllflasche|botella rellenable|bottiglia ricaricabile|garrafa recarregável
concepts|waste sorting|فرز زبالة|فرز النفايات|tri des déchets|Mülltrennung|separación de residuos|raccolta differenziata|separação de resíduos
concepts|recycling|إعادة تدوير|إعادة تدوير|recyclage|Wiederverwertung|reciclaje|riciclo|reciclagem
concepts|reuse|استعمله تاني|إعادة الاستخدام|réutilisation|Wiederverwendung|reutilización|riutilizzo|reutilização
concepts|repair culture|نصلّح بدل ما نرمي|ثقافة الإصلاح|culture de la réparation|Reparaturkultur|cultura de reparación|cultura della riparazione|cultura do conserto
concepts|food waste|أكل بيترمي|هدر الطعام|gaspillage alimentaire|Lebensmittelverschwendung|desperdicio alimentario|spreco alimentare|desperdício de alimentos
actions|reduce waste|قلّل الزبالة|قلّل النفايات|réduire les déchets|Abfall reduzieren|reducir residuos|ridurre i rifiuti|reduzir resíduos
technology|solar heater|سخان شمسي|سخان شمسي|chauffe-eau solaire|Solarboiler|calentador solar|scaldacqua solare|aquecedor solar
technology|wind turbine|توربينة هوا|توربين رياح|éolienne|Windkraftanlage|aerogenerador|turbina eolica|turbina eólica
technology|energy meter|عداد كهربا|عداد كهرباء|compteur d'énergie|Energiezähler|contador de energía|contatore energetico|medidor de energia
actions|conserve energy|وفّر طاقة|وفّر الطاقة|économiser l'énergie|Energie sparen|ahorrar energía|risparmiare energia|economizar energia
concepts|sustainability|استدامة|استدامة|durabilité écologique|Nachhaltigkeit|sostenibilidad|sostenibilità|sustentabilidade
concepts|shared responsibility|مسؤوليتنا كلنا|مسؤولية مشتركة|responsabilité collective|geteilte Verantwortung|responsabilidad compartida|responsabilità condivisa|responsabilidade compartilhada`},
    ]},
];

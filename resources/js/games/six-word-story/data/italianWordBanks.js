// Copyright (c) 2026 Mohamed Magdy. Adapted with owner-confirmed permission.
// Source: https://github.com/mohammedAteff/6word/blob/7d20671ea32efc6c3571b7d1e2260204b9eba49a/src/data/italianWordBanks.ts
/**
 * Italian language bank for the public game.
 *
 * These rounds are intentionally local and deterministic so Italian works
 * without an API key. Endless AI remains an optional variety layer.
 */
export const ITALIAN_ROUND_BANK = {
  easy: [
    ["viaggio","aeroporto","passaporto","timbro","ricordo","casa"],
    ["mare","barca","capitano","tempesta","coraggio","salvezza"],
    ["scuola","maestra","libro","domanda","risposta","curiosità"],
    ["giardino","seme","terra","acqua","fiore","sorriso"],
    ["cucina","pane","tavola","ospite","benvenuto","amicizia"],
    ["mercato","cesto","mela","prezzo","spesa","cena"],
    ["treno","stazione","biglietto","sedile","dialogo","amico"],
    ["ospedale","medico","cura","ripresa","speranza","vita"],
    ["musica","pianoforte","melodia","ricordo","nostalgia","casa"],
    ["fattoria","campo","pioggia","raccolto","gratitudine","abbondanza"],
    ["compleanno","candela","desiderio","sogno","impegno","successo"],
    ["montagna","sentiero","cima","sforzo","traguardo","fiducia"],
    ["biblioteca","scaffale","romanzo","eroe","coraggio","cambiamento"],
    ["finestra","tendina","sole","polvere","colore","stanza"],
    ["regalo","nastro","scatola","biglietto","grazie","sorpresa"]
  ],
  medium: [
    ["mercato","moneta","scelta","rischio","esperienza","crescita"],
    ["automobile","viaggio","sconosciuto","lettera","ricordo","ritorno"],
    ["fotografia","ritratto","volto","storia","segreto","fiducia"],
    ["fiume","ponte","viaggiatore","decisione","coraggio","inizio"],
    ["ufficio","riunione","idea","obiezione","dialogo","successo"],
    ["teatro","attore","sipario","silenzio","tensione","applauso"],
    ["telefono","messaggio","appuntamento","attesa","ansia","ricongiungimento"],
    ["albergo","chiave","camera","finestra","città","avventura"],
    ["orologio","scadenza","ritardo","scusa","onestà","rispetto"],
    ["villaggio","nonna","racconto","saggezza","decisione","futuro"],
    ["museo","statua","storia","domanda","identità","radici"],
    ["gara","rivale","sconfitta","lezione","perseveranza","vittoria"],
    ["faro","nebbia","corno","naufragio","ricerca","soccorso"],
    ["archivio","mappa","frammento","origine","indizio","scoperta"],
    ["orchestra","direttore","pausa","rumore","armonia","incanto"]
  ],
  hard: [
    ["osservatorio","stella","costellazione","mito","domanda","meraviglia"],
    ["officina","progetto","martello","telaio","pazienza","mestiere"],
    ["serra","germoglio","vetro","calore","siccità","adattamento"],
    ["codice","revisione","errore","collaborazione","rilascio","fiducia"],
    ["isola","bussola","corrente","spedizione","pericolo","ritorno"],
    ["asta","eredità","offerta","incertezza","scelta","conseguenza"],
    ["migrazione","ala","meteo","percorso","riposo","sopravvivenza"],
    ["manoscritto","inchiostro","traduzione","margine","autore","eredità"],
    ["gola","eco","alpinista","corda","paura","coraggio"],
    ["circuito","scintilla","corrente","guasto","diagnosi","svolta"],
    ["inverno","disgelo","alluvione","sacco","vicino","solidarietà"],
    ["tribunale","prova","testimone","dubbio","verdetto","misericordia"],
    ["foresta","incendio","fumo","sentiero","deviazione","destino"],
    ["ponte","crepa","riparazione","scadenza","squadra","fiducia"],
    ["galleria","scultura","ombra","significato","riflessione","memoria"]
  ],
  extreme: [
    ["eclissi","oracolo","previsione","presagio","testimone","resa dei conti"],
    ["palinsesto","cancellazione","bordo","codice","lettore","rinascita"],
    ["catacomba","candela","iscrizione","discesa","segreto","ritorno"],
    ["fisica quantistica","paradosso","misura","dubbio","teoria","revisione"],
    ["monsone","gola","ponte","crollo","deviazione","destinazione"],
    ["torre dell'orologio","corvo","lettera","alibi","silenzio","verdetto"],
    ["abisso","sonar","profondità","segnale","superficie","contatto"],
    ["banca dei semi","siccità","genoma","contadino","promessa","futuro"],
    ["maschera","specchio","impostore","prova","rivelazione","identità"],
    ["meridiana","ombra","impero","rovina","archeologo","ricordo"],
    ["tuono","telegrafo","confine","messaggio","tregua","pace"],
    ["labirinto","filo","minotauro","scelta","uscita","patria"],
    ["profezia","enigma","tempio","sacrificio","speranza","destino"],
    ["pelle d'oca","sussurro","registratore","prova","negazione","confessione"],
    ["teatro d'ombre","luce","figura","palco","svolta","verità"]
  ]
};

export const ITALIAN_WILD_BANK = {
  places:["aeroporto","borgo","ospedale","stadio","biblioteca","cortile","faro","mercato coperto","terrazza","sentiero","stazione","ferrovia","osservatorio","fattoria","porto","isola","cascata","parco giochi","officina","galleria","teatro","ponte","campeggio","vicolo","municipio","panetteria","museo","piscina","zoo","chiesa","castello","galleria commerciale","parco","cinema","università","farmacia","sentiero alpino","traghetto","grotta","piazza"],
  people:["pilota","insegnante","cuoca","dentista","ingegnere","musicista","ballerino","scienziata","giornalista","artista","giardiniere","marinaio","guida","detective","atleta","designer","idraulico","falegname","studente","vicino","sindaca","cassiera","redattore","volontaria","medica","vigile del fuoco","fotografo","ricercatrice","panettiere","giudice","meccanico","architetta","pittore","cantante","allenatore","programmatore","venditrice","avvocato","infermiere","capitana"],
  objects:["taccuino","bussola","torcia","valigia","chiave","bottiglia","specchio","ombrello","zaino","fischietto","righello","macchina fotografica","scala","cuscino","coperta","cucchiaio","tazza","corda","secchio","puzzle","candela","bottone","orologio","cartolina","diario","busta","forbici","martello","chitarra","scacchiera","teiera","lanterna","occhiali","portafoglio","bicicletta","passaporto","bandiera","globo","binocolo","sveglia"],
  nature:["brezza","tuono","nuvola","tramonto","alba","bosco","prato","deserto","ghiacciaio","vulcano","riva del fiume","cascata","oceano","gola","collina","foglia","pino","felce","canto degli uccelli","luce lunare","tempesta","pioggerella","nebbia","luce del sole","fulmine","ruscello","muschio","edera","quercia","acero","salice","arcobaleno","rugiada","gelo","ciottolo","luce stellare","scogliera","grotta","costa dell'isola","campo fiorito"],
  food:["mela","banana","arancia","pera","uva","melone","pesca","limone","riso","fagiolo","zuppa","insalata","pasta","crêpe","cialda","muffin","miele","burro","oliva","pomodoro","cetriolo","cipolla","aglio","pizza","pane","formaggio","yogurt","patata","carota","ciliegia","prugna","albicocca","fico","mandorla","noce","marmellata","cioccolato","torta","pretzel","gelato"],
  technology:["cellulare","tastiera","mouse","monitor","router","cuffie","microfono","batteria","cavo","sensore","drone","robot","fotocamera","proiettore","altoparlante","browser","password","server cloud","segnale","pixel","programma","rete","backup","aggiornamento","tablet","portatile","stampante","scanner","modem","microchip","webcam","laser","ologramma","console","joystick","smartwatch","database","firewall","chatbot","algoritmo"],
  feelings:["calma","curiosità","orgoglio","vergogna","sollievo","preoccupazione","sorpresa","gioia","solitudine","gentilezza","pazienza","invidia","coraggio","fiducia","dubbio","desiderio","stupore","gratitudine","imbarazzo","sicurezza","tenerezza","energia","conforto","determinazione","speranza","rabbia","tristezza","entusiasmo","noia","rimorso","amore","ammirazione","frustrazione","pace","calore","paura","serenità","meraviglia","compassione","attesa"],
  actions:["andare","salire","disegnare","dipingere","cantare","ballare","ascoltare","chiedere","rispondere","costruire","riparare","piantare","misurare","scegliere","cambiare","ricordare","invitare","proteggere","scoprire","immaginare","esercitarsi","viaggiare","sussurrare","festeggiare","correre","nuotare","cucinare","leggere","scrivere","ridere","piangere","cercare","aspettare","aprire","chiudere","lanciare","afferrare","sognare","condividere","imparare"],
  concepts:["libertà","giustizia","caso","memoria","sogno","linguaggio","cultura","schema","equilibrio","scopo","curiosità","mistero","cambiamento","futuro","passato","inizio","fine","promessa","scelta","avventura","rispetto","speranza","saggezza","responsabilità","fantasia","fallimento","progresso","identità","comunità","verità","ordine","caos","bellezza","conoscenza","destino","patria","perdono","pensiero","pazienza","fiducia"],
  animals:["volpe","lupo","coniglio","cavallo","mucca","maiale","gallina","anatra","oca","tartaruga","delfino","balena","squalo","polpo","pinguino","gufo","pappagallo","passero","ape","farfalla","scoiattolo","riccio","cammello","asino","leone","tigre","orso","cervo","capra","pecora","scimmia","giraffa","zebra","canguro","koala","lontra","foca","pavone","aquila","falco"],
  work:["ingegnere","insegnante","infermiere","designer","autore","redattore","muratore","agricoltore","autista","allenatore","avvocato","contabile","artista","ricercatore","cuoco","manager","riunione","scadenza","progetto","stipendio","turno","cliente","ufficio","pausa","compito","rapporto","contratto","pianificazione","squadra","carriera","strumento","fabbrica","negozio","appuntamento","telefonata","email","presentazione","colloquio","incarico","collega"],
  time:["martedì","mercoledì","giovedì","venerdì","alba","crepuscolo","mattina","pomeriggio","mezzanotte","fine settimana","festività","stagione","minuto","istante","oggi","domani","ieri","presto","mai","a volte","spesso","primavera","estate","inverno","autunno","lunedì","sabato","domenica","mezzogiorno","sera","notte","settimana","mese","anno","gennaio","aprile","dicembre","adesso","dopo","sempre"]
};

// Copyright (c) 2026 Mohamed Magdy. Adapted with owner-confirmed permission.
// Source: https://github.com/mohammedAteff/6word/blob/7d20671ea32efc6c3571b7d1e2260204b9eba49a/src/data/germanWordBanks.ts
/**
 * German language bank for the public game.
 *
 * The bank is local and deterministic so German works without an API key.
 * Gemini can still be selected separately for on-demand German rounds.
 */
export const GERMAN_ROUND_BANK = {
  easy: [
    ["Bücherei","Buch","Seite","Frage","Antwort","Neugier"],
    ["Teich","Ente","Welle","Stein","Sprung","lachen"],
    ["Küche","Rezept","Schüssel","mischen","Ofen","Keks"],
    ["Bushaltestelle","Regenschirm","Regen","Warten","winken","Ankunft"],
    ["Welpe","Leine","Park","laufen","holen","Freude"],
    ["Morgen","Wecker","Dusche","Kaffee","Schuhe","bereit"],
    ["Garage","Rad","Werkzeug","Reparatur","Probe","Fahrt"],
    ["Festival","Ticket","Musik","Tanz","Freund","Erinnerung"],
    ["Fluss","Brücke","Boot","Strömung","Ruder","Abenteuer"],
    ["Fenster","Vorhang","Sonnenstrahl","Staub","Farbe","Zimmer"],
    ["Geschenk","Band","Kiste","Notiz","danke","Überraschung"],
    ["Team","Plan","Übung","Spiel","Jubel","Sieg"],
    ["Garten","Samen","Erde","Wasser","Blume","Lächeln"],
    ["Markt","Korb","Apfel","Waage","Preis","Abendessen"],
    ["Klassenzimmer","Bleistift","Zeichnung","Idee","Frage","Antwort"]
  ],
  medium: [
    ["Sternwarte","Stern","Karte","Muster","Frage","Einsicht"],
    ["Hafen","Anker","Gezeit","Kapitän","Sturm","Sicherheit"],
    ["Werkstatt","Bauplan","Hammer","Rahmen","Geduld","Handwerk"],
    ["Galerie","Skulptur","Licht","Schatten","Bedeutung","Reflexion"],
    ["Obstgarten","Leiter","Apfel","Korb","Ernte","Dankbarkeit"],
    ["Radio","Rauschen","Stimme","Frequenz","Nachricht","Antwort"],
    ["Hof","Brunnen","Treffen","Versprechen","Vertrauen","Rückkehr"],
    ["Bahnhof","Gleis","Verspätung","Gespräch","Freundschaft","Reise"],
    ["Klinik","Termin","Diagnose","Behandlung","Genesung","Hoffnung"],
    ["Berg","Pfad","Markierung","Gipfel","Wetter","Entscheidung"],
    ["Zeitung","Schlagzeile","Debatte","Prüfung","Perspektive","Wahrheit"],
    ["Theater","Vorhang","Schauspieler","Szene","Fehler","Applaus"],
    ["Archiv","Karte","Brief","Reise","Geheimnis","Entdeckung"],
    ["Dach","Antenne","Signal","Gewitter","Stromausfall","Kerze"],
    ["Museum","Artefakt","Etikett","Frage","Geschichte","Identität"]
  ],
  hard: [
    ["Inselgruppe","Kompass","Strömung","Insel","Expedition","Rückkehr"],
    ["Uhrmacher","Zahnrad","Feder","Spannung","Reparatur","Präzision"],
    ["Gewächshaus","Keimling","Glas","Hitze","Dürre","Anpassung"],
    ["Codebasis","Umbau","Test","Veröffentlichung","Wartung","Vertrauen"],
    ["Leuchtturm","Nebel","Horn","Schiffbruch","Suche","Rettung"],
    ["Auktion","Erbstück","Gebot","Unsicherheit","Wahl","Entscheidung"],
    ["Wanderung","Flügel","Wetter","Route","Rast","Überleben"],
    ["Manuskript","Tinte","Übersetzung","Rand","Autor","Vermächtnis"],
    ["Schlucht","Echo","Kletterer","Seil","Angst","Mut"],
    ["Stromkreis","Funke","Strom","Ausfall","Diagnose","Durchbruch"],
    ["Winter","Tauwetter","Hochwasser","Sandsack","Nachbar","Solidarität"],
    ["Archiv","Fragment","Kurator","Ursprung","Debatte","Enthüllung"],
    ["Gericht","Beweis","Zeuge","Zweifel","Urteil","Gnade"],
    ["Wald","Feuer","Rauch","Pfad","Umweg","Ziel"],
    ["Brücke","Riss","Reparatur","Frist","Team","Vertrauen"]
  ],
  extreme: [
    ["Finsternis","Planetenmodell","Vorhersage","Omen","Zeuge","Abrechnung"],
    ["Palimpsest","Auslöschung","Rand","Code","Leser","Wiedergeburt"],
    ["Katakombe","Kerze","Inschrift","Abstieg","Geheimnis","Rückkehr"],
    ["Quantenphysik","Paradox","Messung","Zweifel","Theorie","Revision"],
    ["Monsun","Schlucht","Brücke","Einsturz","Umweg","Bestimmung"],
    ["Uhrturm","Rabe","Brief","Alibi","Schweigen","Urteil"],
    ["Tiefsee","Sonar","Abgrund","Signal","Oberfläche","Kontakt"],
    ["Samenbank","Dürre","Genom","Bauer","Versprechen","Zukunft"],
    ["Maske","Spiegel","Hochstapler","Probe","Enthüllung","Identität"],
    ["Sonnenuhr","Schatten","Imperium","Ruine","Archäologe","Erinnerung"],
    ["Donner","Telegraf","Grenze","Nachricht","Waffenstillstand","Frieden"],
    ["Labyrinth","Faden","Minotaurus","Wahl","Ausgang","Heimat"],
    ["Orakel","Rätsel","Tempel","Prophezeiung","Opfer","Hoffnung"],
    ["Gänsehaut","flüstern","Tonband","Beweis","Leugnung","Geständnis"],
    ["Schattenspiel","Licht","Figur","Bühne","Wendung","Wahrheit"]
  ]
};

export const GERMAN_WILD_BANK = {
  places:["Flughafen","Dorf","Krankenhaus","Stadion","Bibliothek","Schulhof","Leuchtturm","Markthalle","Dachterrasse","Gartenweg","Bahnhof","Eisenbahn","Sternwarte","Bauernhof","Hafen","Insel","Wasserfall","Spielplatz","Werkstatt","Galerie","Theater","Brücke","Zeltplatz","Gasse","Rathaus","Bäckerei","Museum","Schwimmbad","Zoo","Kirche","Burg","Tunnel","Park","Kino","Universität","Apotheke","Bergpfad","Fährhafen","Höhle","Innenhof"],
  people:["Pilot","Lehrer","Köchin","Zahnarzt","Ingenieur","Musiker","Tänzer","Wissenschaftler","Journalist","Künstlerin","Gärtner","Seemann","Reiseführer","Detektiv","Sportler","Designer","Klempner","Tischler","Student","Nachbar","Bürgermeister","Kassierer","Redakteur","Freiwillige","Ärztin","Feuerwehrmann","Fotograf","Forscher","Bäcker","Richter","Mechaniker","Architekt","Maler","Sänger","Trainer","Programmierer","Verkäuferin","Anwalt","Pfleger","Kapitän"],
  objects:["Notizbuch","Kompass","Taschenlampe","Koffer","Schlüssel","Flasche","Spiegel","Regenschirm","Rucksack","Pfeife","Lineal","Kamera","Leiter","Kissen","Decke","Löffel","Becher","Seil","Eimer","Puzzle","Kerze","Knopf","Uhr","Postkarte","Tagebuch","Briefumschlag","Schere","Hammer","Gitarre","Schachbrett","Teekanne","Laterne","Brille","Geldbörse","Fahrrad","Reisepass","Fahne","Globus","Fernglas","Wecker"],
  nature:["Brise","Donner","Wolke","Sonnenuntergang","Sonnenaufgang","Wald","Wiese","Wüste","Gletscher","Vulkan","Flussufer","Wasserfall","Ozean","Schlucht","Hügel","Blatt","Kiefer","Farn","Vogelgesang","Mondlicht","Sturm","Nieselregen","Nebel","Sonnenlicht","Blitz","Bach","Moos","Efeu","Eiche","Ahorn","Weide","Regenbogen","Tau","Frost","Kiesel","Sternlicht","Klippe","Höhle","Inselküste","Blütenfeld"],
  food:["Apfel","Banane","Orange","Birne","Traube","Melone","Pfirsich","Zitrone","Reis","Bohne","Suppe","Salat","Nudel","Pfannkuchen","Waffel","Muffin","Honig","Butter","Olive","Tomate","Gurke","Zwiebel","Knoblauch","Pizza","Brot","Käse","Joghurt","Kartoffel","Karotte","Kirsche","Pflaume","Aprikose","Feige","Mandel","Walnuss","Marmelade","Schokolade","Kuchen","Breze","Eis"],
  technology:["Handy","Tastatur","Maus","Monitor","Router","Kopfhörer","Mikrofon","Batterie","Kabel","Sensor","Drohne","Roboter","Kamera","Projektor","Lautsprecher","Browser","Passwort","Cloud-Server","Signal","Pixel","Programm","Netzwerk","Sicherung","Update","Tablet","Laptop","Drucker","Scanner","Modem","Chip","Webcam","Laser","Hologramm","Konsole","Joystick","Smartwatch","Datenbank","Firewall","Chatbot","Algorithmus"],
  feelings:["Ruhe","Neugier","Stolz","Scham","Erleichterung","Sorge","Überraschung","Freude","Einsamkeit","Freundlichkeit","Geduld","Neid","Mut","Vertrauen","Zweifel","Sehnsucht","Staunen","Dankbarkeit","Verlegenheit","Zuversicht","Zärtlichkeit","Energie","Trost","Entschlossenheit","Hoffnung","Ärger","Trauer","Begeisterung","Langeweile","Reue","Liebe","Bewunderung","Frust","Frieden","Wärme","Furcht","Gelassenheit","Neugierde","Mitgefühl","Erwartung"],
  actions:["gehen","klettern","zeichnen","malen","singen","tanzen","zuhören","fragen","antworten","bauen","reparieren","pflanzen","messen","wählen","ändern","sich erinnern","einladen","schützen","entdecken","sich vorstellen","üben","reisen","flüstern","feiern","laufen","schwimmen","kochen","lesen","schreiben","lachen","weinen","suchen","warten","öffnen","schließen","werfen","fangen","träumen","teilen","lernen"],
  concepts:["Freiheit","Gerechtigkeit","Zufall","Erinnerung","Traum","Sprache","Kultur","Muster","Gleichgewicht","Zweck","Neugier","Geheimnis","Wandel","Zukunft","Vergangenheit","Anfang","Ende","Versprechen","Wahl","Abenteuer","Respekt","Hoffnung","Weisheit","Verantwortung","Fantasie","Misserfolg","Fortschritt","Identität","Gemeinschaft","Wahrheit","Ordnung","Chaos","Schönheit","Wissen","Schicksal","Heimat","Vergebung","Gedanke","Geduld","Vertrauen"],
  animals:["Fuchs","Wolf","Kaninchen","Pferd","Kuh","Schwein","Huhn","Ente","Gans","Schildkröte","Delfin","Wal","Hai","Oktopus","Pinguin","Eule","Papagei","Spatz","Biene","Schmetterling","Eichhörnchen","Igel","Kamel","Esel","Löwe","Tiger","Bär","Hirsch","Ziege","Schaf","Affe","Giraffe","Zebra","Känguru","Koala","Otter","Robbe","Pfau","Adler","Falke"],
  work:["Ingenieur","Lehrer","Pfleger","Designer","Autor","Redakteur","Bauarbeiter","Landwirt","Fahrer","Trainer","Anwalt","Buchhalter","Künstler","Forscher","Koch","Manager","Besprechung","Frist","Projekt","Gehalt","Schicht","Kunde","Büro","Pause","Aufgabe","Bericht","Vertrag","Planung","Team","Karriere","Werkzeug","Fabrik","Laden","Termin","Telefonat","E-Mail","Präsentation","Interview","Auftrag","Kollege"],
  time:["Dienstag","Mittwoch","Donnerstag","Freitag","Morgengrauen","Dämmerung","Morgen","Nachmittag","Mitternacht","Wochenende","Feiertag","Jahreszeit","Minute","Augenblick","Heute","Übermorgen","Gestern","Bald","Nie","Manchmal","Oft","Frühling","Sommer","Winter","Herbst","Montag","Samstag","Sonntag","Mittag","Abend","Nacht","Woche","Monat","Jahr","Januar","April","Dezember","Jetzt","Später","Immer"]
};

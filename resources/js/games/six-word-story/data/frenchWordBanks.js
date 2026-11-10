// Copyright (c) 2026 Mohamed Magdy. Adapted with owner-confirmed permission.
// Source: https://github.com/mohammedAteff/6word/blob/7d20671ea32efc6c3571b7d1e2260204b9eba49a/src/data/frenchWordBanks.ts
/**
 * French language bank for the public game.
 *
 * The local bank gives French players a complete offline experience while
 * Endless AI can provide additional combinations on demand.
 */
export const FRENCH_ROUND_BANK = {
  easy: [
    ["voyage","aéroport","passeport","tampon","souvenir","maison"],
    ["mer","bateau","capitaine","tempête","courage","sauvetage"],
    ["école","maîtresse","livre","question","réponse","curiosité"],
    ["jardin","graine","terre","eau","fleur","sourire"],
    ["cuisine","pain","table","invité","accueil","amitié"],
    ["marché","panier","pomme","prix","achat","dîner"],
    ["train","gare","billet","siège","discussion","ami"],
    ["hôpital","médecin","soin","guérison","espoir","vie"],
    ["musique","piano","mélodie","souvenir","nostalgie","maison"],
    ["ferme","champ","pluie","récolte","gratitude","abondance"],
    ["anniversaire","bougie","souhait","rêve","effort","succès"],
    ["montagne","sentier","sommet","effort","réussite","confiance"],
    ["bibliothèque","étagère","roman","héros","courage","changement"],
    ["fenêtre","rideau","soleil","poussière","couleur","chambre"],
    ["cadeau","ruban","boîte","message","merci","surprise"]
  ],
  medium: [
    ["marché","monnaie","choix","risque","expérience","croissance"],
    ["voiture","voyage","inconnu","lettre","souvenir","retour"],
    ["photographie","portrait","visage","histoire","secret","confiance"],
    ["rivière","pont","voyageur","décision","courage","début"],
    ["bureau","réunion","idée","objection","dialogue","succès"],
    ["théâtre","acteur","rideau","silence","tension","applaudissement"],
    ["téléphone","message","rendez-vous","attente","anxiété","retrouvailles"],
    ["hôtel","clé","chambre","fenêtre","ville","aventure"],
    ["horloge","délai","retard","excuse","honnêteté","respect"],
    ["village","grand-mère","récit","sagesse","décision","avenir"],
    ["musée","statue","histoire","question","identité","racines"],
    ["course","rival","défaite","leçon","persévérance","victoire"],
    ["phare","brouillard","sirène","naufrage","recherche","secours"],
    ["archive","carte","fragment","origine","indice","découverte"],
    ["orchestre","chef","pause","bruit","harmonie","enchantement"]
  ],
  hard: [
    ["observatoire","étoile","constellation","mythe","question","émerveillement"],
    ["atelier","plan","marteau","cadre","patience","métier"],
    ["serre","pousse","verre","chaleur","sécheresse","adaptation"],
    ["code","révision","erreur","collaboration","publication","confiance"],
    ["île","boussole","courant","expédition","danger","retour"],
    ["enchère","héritage","offre","incertitude","choix","conséquence"],
    ["migration","aile","météo","itinéraire","repos","survie"],
    ["manuscrit","encre","traduction","marge","auteur","héritage"],
    ["gorge","écho","alpiniste","corde","peur","courage"],
    ["circuit","étincelle","courant","panne","diagnostic","déclic"],
    ["hiver","dégel","inondation","sac","voisin","solidarité"],
    ["tribunal","preuve","témoin","doute","verdict","pardon"],
    ["forêt","incendie","fumée","sentier","détour","destin"],
    ["pont","fissure","réparation","délai","équipe","confiance"],
    ["galerie","sculpture","ombre","signification","réflexion","mémoire"]
  ],
  extreme: [
    ["éclipse","oracle","prédiction","présage","témoin","règlement de comptes"],
    ["palimpseste","effacement","bord","code","lecteur","renaissance"],
    ["catacombe","bougie","inscription","descente","mystère","retour"],
    ["physique quantique","paradoxe","mesure","doute","théorie","révision"],
    ["mousson","gorge","pont","effondrement","détour","destination"],
    ["tour de l'horloge","corbeau","lettre","alibi","silence","verdict"],
    ["abîme","sonar","profondeur","signal","surface","contact"],
    ["banque de graines","sécheresse","génome","agriculteur","promesse","avenir"],
    ["masque","miroir","imposteur","épreuve","révélation","identité"],
    ["cadran solaire","ombre","empire","ruine","archéologue","souvenir"],
    ["tonnerre","télégraphe","frontière","message","trêve","paix"],
    ["labyrinthe","fil","minotaure","choix","sortie","patrie"],
    ["prophétie","énigme","temple","sacrifice","espoir","destin"],
    ["chair de poule","murmure","magnétophone","preuve","déni","aveu"],
    ["théâtre d'ombres","lumière","figure","scène","tournant","vérité"]
  ]
};

export const FRENCH_WILD_BANK = {
  places:["aéroport","village","hôpital","stade","bibliothèque","cour d'école","phare","marché couvert","terrasse","sentier","gare","chemin de fer","observatoire","ferme","port","île","cascade","terrain de jeu","atelier","galerie","théâtre","pont","camping","ruelle","mairie","boulangerie","musée","piscine","zoo","église","château","tunnel","parc","cinéma","université","pharmacie","sentier de montagne","ferry","grotte","place"],
  people:["pilote","professeure","cuisinier","dentiste","ingénieure","musicien","danseuse","scientifique","journaliste","artiste","jardinier","marin","guide","détective","athlète","designer","plombier","menuisière","étudiant","voisin","maire","caissier","rédactrice","bénévole","médecin","pompier","photographe","chercheuse","boulanger","juge","mécanicien","architecte","peintre","chanteuse","entraîneur","programmeur","vendeuse","avocat","infirmière","capitaine"],
  objects:["carnet","boussole","lampe torche","valise","clé","bouteille","miroir","parapluie","sac à dos","sifflet","règle","appareil photo","échelle","oreiller","couverture","cuillère","tasse","corde","seau","puzzle","bougie","bouton","montre","carte postale","journal intime","enveloppe","ciseaux","marteau","guitare","échiquier","théière","lanterne","lunettes","portefeuille","vélo","passeport","drapeau","globe","jumelles","réveil"],
  nature:["brise","tonnerre","nuage","coucher de soleil","lever de soleil","forêt","prairie","désert","glacier","volcan","rive","cascade","océan","gorge","colline","feuille","pin","fougère","chant d'oiseau","clair de lune","tempête","bruine","brouillard","rayon de soleil","éclair","ruisseau","mousse","lierre","chêne","érable","saule","arc-en-ciel","rosée","givre","caillou","lumière des étoiles","falaise","grotte","côte insulaire","champ fleuri"],
  food:["pomme","banane","orange","poire","raisin","melon","pêche","citron","riz","haricot","soupe","salade","pâtes","crêpe","gaufre","muffin","miel","beurre","olive","tomate","concombre","oignon","ail","pizza","pain","fromage","yaourt","pomme de terre","carotte","cerise","prune","abricot","figue","amande","noix","confiture","chocolat","gâteau","bretzel","glace"],
  technology:["téléphone","clavier","souris","écran","routeur","écouteurs","microphone","batterie","câble","capteur","drone","robot","caméra","projecteur","haut-parleur","navigateur","mot de passe","serveur distant","signal","pixel","programme","réseau","sauvegarde","mise à jour","tablette","ordinateur portable","imprimante","scanner","modem","puce","webcam","laser","hologramme","console","joystick","montre connectée","base de données","pare-feu","robot conversationnel","algorithme"],
  feelings:["calme","curiosité","fierté","honte","soulagement","inquiétude","surprise","joie","solitude","gentillesse","patience","jalousie","courage","confiance","doute","désir","étonnement","gratitude","embarras","assurance","tendresse","énergie","réconfort","détermination","espoir","colère","tristesse","enthousiasme","ennui","remords","amour","admiration","frustration","paix","chaleur","peur","sérénité","émerveillement","compassion","attente"],
  actions:["aller","monter","dessiner","peindre","chanter","danser","écouter","demander","répondre","construire","réparer","planter","mesurer","choisir","changer","se souvenir","inviter","protéger","découvrir","imaginer","s'entraîner","voyager","chuchoter","célébrer","courir","nager","cuisiner","lire","écrire","rire","pleurer","chercher","attendre","ouvrir","fermer","lancer","attraper","rêver","partager","apprendre"],
  concepts:["liberté","justice","hasard","mémoire","rêve","langage","culture","motif","équilibre","but","curiosité","mystère","changement","avenir","passé","début","fin","promesse","choix","aventure","respect","espoir","sagesse","responsabilité","fantaisie","échec","progrès","identité","communauté","vérité","ordre","chaos","beauté","connaissance","destin","patrie","pardon","pensée","patience","confiance"],
  animals:["renard","loup","lapin","cheval","vache","cochon","poule","canard","oie","tortue","dauphin","baleine","requin","poulpe","manchot","hibou","perroquet","moineau","abeille","papillon","écureuil","hérisson","chameau","âne","lion","tigre","ours","cerf","chèvre","mouton","singe","girafe","zèbre","kangourou","koala","loutre","phoque","paon","aigle","faucon"],
  work:["ingénieur","professeure","infirmier","designer","auteur","éditrice","maçon","agricultrice","conducteur","entraîneuse","avocate","comptable","artiste","chercheur","cuisinière","directeur","réunion","délai","projet","salaire","équipe","client","bureau","pause","tâche","rapport","contrat","planification","carrière","outil","usine","magasin","rendez-vous","appel","courriel","présentation","entretien","mission","collègue","calendrier"],
  time:["mardi","mercredi","jeudi","vendredi","aube","crépuscule","matin","après-midi","minuit","week-end","jour férié","saison","minute","instant","aujourd'hui","demain","hier","bientôt","jamais","parfois","souvent","printemps","été","hiver","automne","lundi","samedi","dimanche","midi","soir","nuit","semaine","mois","année","janvier","avril","décembre","maintenant","plus tard","toujours"]
};

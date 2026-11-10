// Copyright (c) 2026 Mohamed Magdy. Adapted with owner-confirmed permission.
// Source: https://github.com/mohammedAteff/6word/blob/7d20671ea32efc6c3571b7d1e2260204b9eba49a/src/data/extraWordBanks.ts
/**
 * Expansion packs for the curated bank.
 *
 * Keeping additions in a separate file lets us grow the game without making
  * the original, hand-tuned bank harder to review. `gameEngine.ts` combines these
 * rows with the base bank at startup.
 */
export const EXTRA_ROUND_BANK = {
  en: {
    easy: [
      ["beach","sand","castle","child","laughter","summer"],
      ["bicycle","pedal","hill","breath","finish","pride"],
      ["coffee","morning","window","sunlight","routine","peace"],
      ["rain","boots","puddle","jump","play","smile"],
      ["gift","wrap","surprise","thanks","hug","belonging"],
      ["bus","driver","route","lost","question","direction"],
      ["bakery","dough","oven","scent","neighbor","community"]
    ],
    medium: [
      ["passport","border","question","answer","lie","consequence"],
      ["gallery","painting","color","childhood","grief","healing"],
      ["farmer","seed","wait","drought","patience","harvest"],
      ["engine","failure","garage","invention","risk","breakthrough"],
      ["letter","envelope","stamp","rumor","truth","reconciliation"],
      ["carnival","mask","music","crowd","disguise","reveal"],
      ["balcony","neighbor","plant","neglect","water","bloom"]
    ],
    hard: [
      ["anchor","harbor","departure","silence","guilt","forgiveness"],
      ["clock","shadow","midnight","footstep","intruder","courage"],
      ["thread","needle","fabric","pattern","identity","transformation"],
      ["comet","sky","telescope","scientist","doubt","discovery"],
      ["court","verdict","witness","memory","bias","justice"],
      ["fence","gate","warning","curiosity","trespass","secret"],
      ["drum","parade","rhythm","tradition","rebellion","renewal"]
    ],
    extreme: [
      ["ticket","locker","swimmer","echo","promise","ocean"],
      ["ink","mirror","signature","contract","freedom","responsibility"],
      ["mushroom","forest","map","fog","myth","truth"],
      ["ash","garden","seed","letter","grave","legacy"],
      ["crow","clockwork","rooftop","thunder","inventor","wonder"],
      ["siren","elevator","button","power","darkness","trust"],
      ["ribbon","train","platform","childhood","farewell","adventure"]
    ]
  },
  ar: {
    easy: [
      ["الشاطئ","الرمل","قلعة","طفل","ضحك","الصيف"],
      ["دراجة","دواسة","تل","نَفَس","خط النهاية","فخر"],
      ["قهوة","صباح","نافذة","ضوء","روتين","سلام"],
      ["مطر","حذاء","بركة","قفزة","لعب","ابتسامة"],
      ["هدية","غلاف","مفاجأة","شكر","عناق","انتماء"],
      ["حافلة","سائق","طريق","ضياع","سؤال","اتجاه"],
      ["مخبز","عجين","فرن","رائحة","جار","مجتمع"]
    ],
    medium: [
      ["جواز سفر","حدود","سؤال","إجابة","كذبة","عاقبة"],
      ["معرض","لوحة","لون","طفولة","حزن","شفاء"],
      ["مزارع","بذرة","انتظار","جفاف","صبر","حصاد"],
      ["محرك","عطل","مرآب","اختراع","مخاطرة","إنجاز"],
      ["رسالة","ظرف","طابع","إشاعة","حقيقة","مصالحة"],
      ["مهرجان","قناع","موسيقى","حشد","تنكر","انكشاف"],
      ["شرفة","جار","نبتة","إهمال","ماء","إزهار"]
    ],
    hard: [
      ["مرساة","ميناء","رحيل","صمت","ذنب","غفران"],
      ["ساعة","ظل","منتصف الليل","خطوة","متسلل","شجاعة"],
      ["خيط","إبرة","قماش","نقش","هوية","تحول"],
      ["مذنب","سماء","تلسكوب","عالم","شك","اكتشاف"],
      ["محكمة","حكم","شاهد","ذاكرة","تحيز","عدالة"],
      ["سياج","بوابة","تحذير","فضول","تعدٍ","سر"],
      ["طبل","موكب","إيقاع","تقليد","تمرد","تجدد"]
    ],
    extreme: [
      ["تذكرة","خزانة","سباح","صدى","وعد","محيط"],
      ["حبر","مرآة","توقيع","عقد","حرية","مسؤولية"],
      ["فطر","غابة","خريطة","ضباب","أسطورة","حقيقة"],
      ["رماد","حديقة","بذرة","رسالة","قبر","إرث"],
      ["غراب","آلية","سطح","رعد","مخترع","دهشة"],
      ["صفارة","مصعد","زر","طاقة","ظلام","ثقة"],
      ["شريط","قطار","رصيف","طفولة","وداع","مغامرة"]
    ]
  },
  "ar-eg": {
    easy: [
      ["شاطئ","رمل","قلعة","طفل","ضحك","صيف"],
      ["عجلة","بدال","طلعة","نفس","خط النهاية","فخر"],
      ["قهوة","الصبح","شباك","نور الشمس","روتين","راحة"],
      ["مطر","جزمة","بركة","نطة","لعب","ابتسامة"],
      ["هدية","ورق","مفاجأة","شكر","حضن","انتماء"],
      ["أتوبيس","سواق","خط سير","تايه","سؤال","اتجاه"],
      ["مخبز","عجين","فرن","ريحة","جار","ناس"]
    ],
    medium: [
      ["باسبور","حدود","سؤال","إجابة","كدبة","نتيجة"],
      ["جاليري","لوحة","لون","طفولة","حزن","تعافي"],
      ["فلاح","بذرة","استنى","جفاف","صبر","محصول"],
      ["موتور","عطل","ورشة","اختراع","مجازفة","إنجاز"],
      ["جواب","ظرف","طابع","إشاعة","حقيقة","صلح"],
      ["مهرجان","ماسك","مزيكا","زحمة","تنكر","انكشاف"],
      ["بلكونة","جار","نبتة","إهمال","ميّه","تفتح"]
    ],
    hard: [
      ["هلب","مينا","رحيل","سكوت","ذنب","سماح"],
      ["ساعة","ضل","نص الليل","خطوة","حرامي","شجاعة"],
      ["خيط","إبرة","قماش","رسمة","هوية","تغيير"],
      ["مذنب","سما","تلسكوب","عالم","شك","اكتشاف"],
      ["محكمة","حكم","شاهد","ذاكرة","تحيز","عدل"],
      ["سور","بوابة","تحذير","فضول","تعدي","سر"],
      ["طبلة","موكب","إيقاع","عادة","تمرد","تجديد"]
    ],
    extreme: [
      ["تذكرة","لوكر","سباح","صدى","وعد","بحر"],
      ["حبر","مراية","إمضا","عقد","حرية","مسؤولية"],
      ["عيش الغراب","غابة","خريطة","شبورة","أسطورة","حقيقة"],
      ["رماد","جنينة","بذرة","جواب","قبر","سيرة"],
      ["غراب","تروس","سطح","رعد","مخترع","دهشة"],
      ["سرينة","أسانسير","زرار","كهربا","ضلمة","ثقة"],
      ["شريط","قطر","رصيف","طفولة","وداع","مغامرة"]
    ]
  }
};

/** Extra entries for Wild Mix. Each shelf stays independent so six shelves
 * can still be sampled without accidentally turning the mode into a chain. */
export const EXTRA_WILD_BANK = {
  en: {
    places:["cottage","alley","park","observatory"],
    people:["barber","architect","referee","courier"],
    objects:["teapot","hammer","diary","globe"],
    nature:["raindrop","glacier","orchard","sunset"],
    food:["waffle","olive","pumpkin","biscuit"],
    technology:["turbine","microphone","portal","router"],
    feelings:["awe","shame","delight","longing"],
    actions:["juggle","pause","climb","sketch"],
    concepts:["equity","mystery","origin","habit"],
    animals:["badger","dolphin","parrot","rabbit"],
    work:["payroll","shift","proposal","archive"],
    time:["dawn","Friday","era","afternoon"]
  },
  ar: {
    places:["كوخ","زقاق","حديقة","مرصد"],
    people:["حلاق","مهندس","حكم","ساعي بريد"],
    objects:["إبريق","مطرقة","مذكرات","كرة أرضية"],
    nature:["قطرة مطر","جليد","بستان","غروب"],
    food:["فطيرة","زيتون","يقطين","بسكويت"],
    technology:["توربين","ميكروفون","بوابة","موجّه شبكة"],
    feelings:["رهبة","خجل","بهجة","اشتياق"],
    actions:["يلاعب","يتوقف","يتسلق","يرسم"],
    concepts:["إنصاف","لغز","أصل","عادة"],
    animals:["غرير","دلفين","ببغاء","أرنب"],
    work:["رواتب","وردية","اقتراح","أرشيف"],
    time:["الفجر","الجمعة","حقبة","بعد الظهر"]
  },
  "ar-eg": {
    places:["كوخ","حارة","جنينة","مرصد"],
    people:["حلاق","مهندس","حكم","ساعي"],
    objects:["براد","شاكوش","مذكرات","كرة أرضية"],
    nature:["نقطة مطر","تلج","بستان","غروب"],
    food:["وافل","زيتونة","قرع","بسكوت"],
    technology:["توربينة","ميكروفون","بوابة","راوتر"],
    feelings:["رهبة","كسوف","فرحة","اشتياق"],
    actions:["يلاعب","يفصل","يتسلّق","يخطّط"],
    concepts:["حق","لغز","أصل","عادة"],
    animals:["غرير","دولفين","بغبغان","أرنب"],
    work:["مرتبات","وردية","اقتراح","أرشيف"],
    time:["الفجر","الجمعة","عصر","بعد الضهر"]
  }
};

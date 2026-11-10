/**
 * Original Cairene story vocabulary, grouped into everyday situations.
 * Reference coverage: public publisher descriptions and contents, not full-book review.
 * Samia Louis, Kallimni 'Arabi Bishweesh: https://aucpress.com/9789774162206/
 * Al-Tonsi, Al-Sawi and Massoud, Kalaam Gamiil 1: https://aucpress.com/9789774163159/
 * Woidich and Heinen-Nasr, kullu tamam!: https://aucpress.com/9789774248429/
 * Wightwick and Gaafar, Colloquial Arabic of Egypt:
 * https://www.routledge.com/Colloquial-Arabic-of-Egypt-The-Complete-Course-for-Beginners/Wightwick-Gaafar/p/book/9781138958036
 * Each scene contains three original six-word paths. No textbook lists or dialogues are reproduced.
 */
export const EGYPTIAN_SCENE_GROUPS = [
    {name: 'Neighbourhood errands', scenes: [
        {name: 'Buying baladi bread', entries: `places|فرن عيش
people|فرّان
objects|كيس عيش
food|رغيف سخن
actions|استنى دورك
feelings|ريحة تفتح النفس
time|قبل الزحمة
objects|كارت التموين
people|اللي قدامك
actions|عدّ الأرغفة
concepts|حقك في الدور
feelings|مستعجل
food|عيش محمّص
objects|سبت العيش
actions|غطّي العيش
places|تحت الشباك
time|بدري بدري
concepts|رزق اليوم`},
        {name: 'At the corner grocery', entries: `places|بقالة الناصية
people|عمّ البقال
food|علبة جبنة
objects|كيس طلبات
actions|حاسب على الطلبات
concepts|باقي الفلوس
food|شاي سايب
objects|باكو بسكوت
work|دفتر الحساب
actions|اكتب على النوتة
time|آخر الشهر
feelings|أمانة
food|علبة سمنة
objects|رف عالي
actions|ناولني الكيس
people|زبون قديم
concepts|على الحساب
feelings|حسن معاملة`},
        {name: 'At the local market', entries: `places|سوق الجمعة
people|بياع خضار
objects|عربية كارو
food|حزمة جرجير
actions|نقّي الخضار
concepts|كيلو إلا ربع
food|حبة طماطم
objects|كفّة الميزان
actions|زوّد شوية
concepts|سعر الجملة
feelings|راضي بالسعر
time|قبل ما يقفل
food|بطاطس للتحمير
objects|شوال بصل
people|زبونة بتفاصل
actions|شيل الأكياس
places|فرشة على الأرض
concepts|لقمة عيش`},
        {name: 'Street breakfast', entries: `places|عربية فول
people|معلم الفول
objects|قدرة فول
food|زيت حار
actions|قلّب الطبق
feelings|جعان قوي
food|طعمية محشية
objects|قرطاس
actions|احشي السندوتش
food|طرشي مشكل
concepts|طلب مخصوص
time|على الصبح
food|طبق بليلة
objects|طبق صاج
people|زميل الفطار
actions|كل على مهلك
feelings|شبعان
concepts|بالصحة والهنا`},
        {name: 'Taking a microbus', entries: `places|موقف ميكروباص
people|تبّاع
objects|كرسي ورا
concepts|الأجرة كام
actions|لمّ الأجرة
places|على أول الشارع
people|الراكب اللي جنبك
objects|باب جرّار
actions|افسح مكان
time|أول ما يتملي
feelings|مزنوق
concepts|مشوار طويل
places|تحت الكوبري
actions|نزّلني هنا
objects|فكّة
people|سواق تايه
concepts|حساب الأجرة
feelings|وصل بالسلامة`},
        {name: 'Finding a street', entries: `places|الناصية التانية
objects|يافطة زرقا
people|صاحب الكشك
actions|اسأل على العنوان
places|وشّ الجامع
concepts|وصف الطريق
places|يمين في شمال
objects|رقم العمارة
actions|ارجع خطوتين
time|خمس دقايق مشي
feelings|مش عارف السكة
concepts|علامة معروفة
places|عند الإشارة
people|حد من المنطقة
actions|خد أول يمين
places|آخر الحارة
feelings|ارتاح لما وصل
concepts|سكة مختصرة`},
    ]},
    {name: 'Home and hospitality', scenes: [
        {name: 'A morning at home', entries: `time|من بدري
objects|كوباية الشاي
food|لقمة جبنة
actions|افتح البلكونة
nature|شمس الصبح
feelings|رايق
objects|هدوم الشغل
people|حد نايم
actions|وطّي صوتك
time|لسه بدري
concepts|عادة كل يوم
feelings|مش مصحصح
objects|مفتاح الشقة
food|آخر حتة عيش
actions|اقفل وراك
places|على السلم
time|قبل الميعاد
concepts|يوم جديد`},
        {name: 'Tidying the flat', entries: `objects|مقشة
objects|جاروف زبالة
places|ركنة الأوضة
actions|اكنس تحت السرير
concepts|ترتيب البيت
feelings|مرتاح في بيته
objects|جردل المسح
food|قشر برتقان
actions|انشر الغسيل
time|يوم التنضيف
objects|ملاية نضيفة
nature|هوا داخل
objects|كيس الزبالة
actions|لمّ الكراكيب
people|حد بيساعد
places|فوق الدولاب
concepts|كل حاجة مكانها
feelings|رايق بعد التعب`},
        {name: 'Welcoming guests', entries: `people|ضيف مفاجئ
objects|جرس الشقة
actions|افتح للضيف
food|صينية شاي
feelings|فرحان بالزيارة
concepts|نورت البيت
objects|طقم الصيني
food|كوباية شربات
actions|هات الكراسي
people|أهل البيت
time|زيارة خفيفة
concepts|البيت بيتك
food|حتة كيك
objects|علبة مناديل
actions|زوّد السكر
time|قبل ما تمشي
feelings|وحشتنا
concepts|زيارة تتردّ`},
        {name: 'Family lunch', entries: `food|صينية بطاطس
objects|غطا الحلة
people|اللي بيغرف
actions|حطّ الأكل
time|على الغدا
feelings|نفسه في أكلة
food|محشي كرنب
objects|طبق غويط
actions|ناولني المعلقة
concepts|نصيب كل واحد
people|أصغر واحد
feelings|مبسوط باللمة
food|بواقي الغدا
objects|علبة تلاجة
actions|شيل الأطباق
time|بعد الأكل
concepts|إيد واحدة
feelings|دعوة من القلب`},
        {name: 'Neighbours in the building', entries: `people|بواب العمارة
places|مدخل العمارة
objects|لمبة السلم
actions|سلّم على الجيران
concepts|صباح الخير
feelings|وش بشوش
people|جار في الدور التالت
objects|مفتاح الأسانسير
actions|امسك الباب
work|مصاريف العمارة
concepts|اتفاق الجيران
time|كل أول شهر
places|السطوح
animals|قطة العمارة
actions|ارمي لها أكل
objects|طبق ميّه
feelings|طيبة قلب
concepts|عِشرة سنين`},
        {name: 'Visiting relatives', entries: `people|خالتك
people|جوز عمتك
objects|علبة حلاوة
actions|زور قرايبك
time|من زمان قوي
feelings|لهفة المقابلة
food|طبق فاكهة
objects|صور العيلة
actions|احكي اللي حصل
people|قريب مسافر
concepts|أخبار العيلة
feelings|مفتقدينه
objects|كيس هدايا
actions|سلّم على تيتة
time|قبل السفر
places|على باب البيت
concepts|ما تقطعش الزيارة
feelings|فراق صعب`},
    ]},
    {name: 'Small problems and repairs', scenes: [
        {name: 'At the shoe repairer', entries: `people|إسكافي
objects|نعل جزمة
objects|فردة شمال
actions|خيط الجزمة
work|شغل متين
feelings|الجزمة مريحة
objects|فرشة تلميع
objects|علبة ورنيش
actions|لمّع الجلد
concepts|على المقاس
time|نص ساعة
work|أجرة التصليح
objects|كعب مكسور
places|دكان صغير
actions|جرّب تمشي
people|صاحب الصنعة
concepts|قديم بس غالي
concepts|رجعت زي الجديدة`},
        {name: 'Clothes alterations', entries: `people|خياطة الحتة
objects|شريط مقاس
objects|دبوس مشبك
actions|قصّر البنطلون
concepts|طول مناسب
feelings|مرتاح في اللبس
objects|زرار ناقص
objects|بكرة لونها أحمر
actions|ركّب السوستة
time|على بكره
work|شغل نضيف
concepts|الطلب جاهز
objects|كم واسع
objects|جيب مقطوع
actions|ضيّق القميص
people|زبون مستني
work|شغل مستعجل
feelings|كده تمام`},
        {name: 'An appliance breaks', entries: `objects|سلك مقطوع
technology|فيشة سايحة
people|فني صيانة
actions|افصل الفيشة
concepts|عطل مفاجئ
feelings|قلقان من التكلفة
technology|موتور المروحة
objects|عدة التصليح
actions|غيّر القطعة
work|قطع غيار
time|لسه في الضمان
concepts|نفس العطل
technology|غسالة بتسرّب
objects|خرطوم مفكوك
actions|جرّب من تاني
work|مصنعية
concepts|اشتغل زي الأول
feelings|ارتاح من القلق`},
        {name: 'Something goes missing', entries: `objects|ميدالية المفاتيح
places|جيب الجاكت
actions|دوّر كويس
people|آخر واحد شافها
time|من شوية
feelings|متلخبط
objects|شنطة مفتوحة
places|تحت الكنبة
actions|اسأل اللي في البيت
concepts|لحظة نسيان
time|قبل ما تتأخر
feelings|مش فاكر
objects|حاجة عزيزة
people|اللي لقاها
actions|بصّ حواليك
places|مكان مش متوقع
concepts|رجعت لصاحبها
feelings|يا فرحتي`},
        {name: 'Phone credit and calls', entries: `technology|كارت شحن
people|بتاع الموبايلات
objects|رقم سري
actions|اشحن الرصيد
concepts|مكالمة مهمة
time|قبل ما الرصيد يخلص
technology|باقة نت
technology|شبكة ضعيفة
actions|جرّب تتصل
people|الطرف التاني
concepts|الخط مشغول
feelings|زهقان من الانتظار
technology|باور بانك
objects|شريحة جديدة
actions|سيب رسالة
time|أول ما يشحن
concepts|مكالمة فايتة
feelings|صوته طمّنّي`},
        {name: 'Paying household bills', entries: `objects|وصل الكهربا
people|المحصّل
technology|عداد بكارت
actions|سدّد الفاتورة
time|آخر يوم للدفع
concepts|مبلغ ناقص
objects|إيصال قديم
work|حساب الشهر
actions|راجع الأرقام
concepts|غلط في الحساب
time|بعد القبض
feelings|شايل همّها
technology|كارت العداد
places|منفذ دفع
actions|احتفظ بالوصل
work|تقسيط
concepts|الموضوع خلص
feelings|الحمل خفّ`},
    ]},
    {name: 'Friends and outings', scenes: [
        {name: 'Meeting friends', entries: `people|صاحب قديم
places|مكان المقابلة
objects|موبايل مقفول
actions|ابعت اللوكيشن
time|على الميعاد
feelings|متحمس للخروجة
objects|ترابيزة فاضية
food|عصير قصب
actions|احجز مكان
people|واحد جاي في السكة
time|اتأخر شوية
concepts|الصحبة الحلوة
places|فسحة قريبة
objects|صورة سيلفي
actions|اضحك من قلبك
time|وقت عدى بسرعة
feelings|نفسي تتعاد
concepts|يوم ما يتنسيش`},
        {name: 'At the neighbourhood ahwa', entries: `places|قهوة بلدي
people|قهوجي
objects|صينية نحاس
food|شاي كشري
actions|اطلب المشروب
time|بعد المغرب
objects|طاولة زهر
food|قهوة مظبوط
people|صاحب القعدة
actions|ارمي الزهر
feelings|رايق البال
concepts|قعدة حلوة
technology|ماتش على التليفزيون
objects|كرسي على الرصيف
food|كوباية ينسون
actions|شجّع فريقك
concepts|حكاوي القهوة
feelings|لسه مش عايز يمشي`},
        {name: 'Birthday preparations', entries: `food|تورتة شوكولاتة
objects|شمع عيد الميلاد
people|صاحب المفاجأة
actions|خبّي الهدية
concepts|سر بيننا
feelings|مستني اللحظة
objects|بلالين ملوّنة
food|أطباق حلويات
actions|زيّن الأوضة
time|قبل ما يرجع
people|آخر ضيف
feelings|مكسوف من المفاجأة
objects|كارت معايدة
technology|فيديو للذكرى
actions|اطفي الشمع
concepts|سنة حلوة
time|نفس اليوم كل سنة
feelings|فرحة من غير كلام`},
        {name: 'A wedding celebration', entries: `people|العريس
people|العروسة
objects|دعوة الفرح
places|قاعة أفراح
actions|بارك للعيلة
feelings|فرحة كبيرة
objects|دبلة
food|شربات ورد
people|صحاب العريس
actions|زغردي
concepts|مبروك
time|ليلة العمر
objects|بوكيه العروسة
technology|سماعات الفرح
actions|صوّر الزفّة
people|أم العروسة
concepts|بيت جديد
feelings|دموع فرح`},
        {name: 'A Nile outing', entries: `places|كورنيش النيل
people|ريس الفلوكة
objects|تذكرة مركب
actions|اطلع الفلوكة
nature|هوا النيل
feelings|مبسوط بالفسحة
food|درة مشوية
objects|كيس تسالي
animals|جوز حمام
actions|بصّ للميّه
time|على المغرب
concepts|فسحة على القدّ
nature|نور على الميّه
places|قعدة على الكورنيش
food|ترمس بالليمون
actions|هات صورة لينا
time|لسه في وقت
feelings|نسي تعب اليوم`},
        {name: 'A trip to Alexandria', entries: `places|محطة رمسيس
objects|شنطة المصيف
people|صاحب الرحلة
actions|احجز القطر
time|أول إجازة
feelings|نفسه يشوف البحر
places|بحري
food|كبدة إسكندراني
nature|ريحة البحر
actions|تمشّى على الكورنيش
objects|شمسية مفتوحة
concepts|مصيف عائلي
food|درة مسلوقة
places|قعدة على الرمل
objects|هدوم بحر
actions|لمّ الشنط
time|قطر الرجوع
feelings|مش عايز الإجازة تخلص`},
    ]},
    {name: 'Plans and everyday conversations', scenes: [
        {name: 'Plans change', entries: `concepts|اتفاق من بدري
people|حد اعتذر
objects|رسالة صوتية
actions|غيّر الخطة
time|مرة تانية
feelings|متضايق شوية
concepts|حصل خير
technology|جروب الصحاب
actions|اسألهم الأول
time|بكره بالليل
people|اللي مشغول
feelings|متردد
concepts|على حسب الظروف
places|مكان بديل
actions|نتقابل في النص
time|مافيش وقت
feelings|مرتاح للقرار
concepts|حل يناسب الكل`},
        {name: 'Clearing up a misunderstanding', entries: `concepts|كلمة اتفهمت غلط
objects|رسالة من غير رد
people|صاحب زعلان
actions|افهم قصده
feelings|واخد على خاطره
time|بعد ما هدى
concepts|مش قصدي
actions|اسمع للآخر
people|حد يصلّح بينهم
feelings|حاسس بذنبه
concepts|بلاش زعل
time|شوية وهيروق
objects|ورقة اعتذار
actions|مدّ إيدك
concepts|صفحة جديدة
feelings|مسامح من قلبه
people|صحاب من تاني
time|زي زمان`},
        {name: 'Asking a favour', entries: `people|صاحب جدع
objects|حاجة تقيلة
actions|شيل معايا
concepts|طلب صغير
time|وقت فاضي
feelings|مكسوف يطلب
concepts|بعد إذنك
actions|استلف الحاجة
people|حد يعتمد عليه
objects|شنطة متروكة
time|لحد ما أرجع
feelings|مطمّن
concepts|ردّ الجميل
actions|اخدم صاحبك
people|ناس وقفت جنبه
time|وقت اللزوم
feelings|فرحان بالمساعدة
concepts|ما نستغناش عنك`},
        {name: 'Agreeing a price', entries: `places|محل أدوات منزلية
people|تاجر شاطر
objects|حاجة في الفاترينة
actions|اسأل على التمن
concepts|غالي عليّا
feelings|نفسه يشتريها
concepts|على قدّ الإيد
objects|فلوس محوّشها
actions|فاصل بهدوء
work|سعر نهائي
time|عرض النهارده
feelings|محتار بين اتنين
concepts|السعر مناسب
actions|خد لفة الأول
objects|الحاجة في الكيس
people|زبون فاهم
work|بيع وشراء
feelings|مبسوط بالشروة`},
        {name: 'First day at work', entries: `work|أول يوم شغل
people|زميل جديد
objects|كارت الدخول
actions|عرّف نفسك
time|قبل المدير
feelings|متوتر من البداية
places|مكتب فاضي
technology|إيميل الشغل
work|تعليمات جديدة
actions|اسأل من غير كسوف
people|حد يشرح لك
concepts|واحدة واحدة
work|مهمة بسيطة
objects|ورقة ملاحظات
actions|خلّص المطلوب
time|آخر اليوم
feelings|أخد على المكان
concepts|بداية موفّقة`},
        {name: 'Learning to drive', entries: `people|مدرب سواقة
objects|عربية مانيوال
technology|دبرياج
actions|دوّر العربية
concepts|أول درس سواقة
feelings|خايف يغلط
objects|مراية جنب
places|شارع فاضي
actions|هدّي السرعة
concepts|على مهلك
time|قبل الملف
feelings|ثقة بتزيد
objects|فرامل إيد
places|مكان للركنة
actions|اركن براحة
work|رخصة سواقة
concepts|اتعلّم من غلطه
feelings|فرحان بنفسه`},
    ]},
    {name: 'Seasons and familiar occasions', scenes: [
        {name: 'An iftar gathering', entries: `objects|فانوس رمضان
food|بلح منقوع
people|صايم
actions|جهّز الفطار
time|قبل أذان المغرب
feelings|مستني الفطار
food|سمبوسك
objects|صينية كنافة
people|ضيف على الفطار
actions|اعزم الجيران
concepts|لمة رمضان
time|أول يوم صيام
food|قطايف
objects|طبق للجيران
actions|وزّع الأكل
places|مائدة الرحمن
time|بعد التراويح
feelings|راحة ورضا`},
        {name: 'Eid visits', entries: `time|صباح العيد
objects|هدوم العيد
food|كحك
people|صاحب العيدية
actions|عيّد على الناس
feelings|فرحة العيد
objects|ظرف عيدية
food|بسكوت العيد
people|أولاد خالتك
actions|زور الكبير
concepts|كل سنة وإنت طيب
time|أول أيام العيد
food|غريبة
places|جنينة قريبة
objects|لعبة جديدة
actions|فرّح العيال
time|قبل الإجازة ما تخلص
feelings|نفسه العيد يطوّل`},
        {name: 'A school morning', entries: `objects|شنطة المدرسة
food|سندوتش الفسحة
people|ماما
actions|صحّي العيال
time|الطابور الصبح
feelings|مش عايز يصحى
objects|كراسة الواجب
objects|مقلمة
people|ميس الفصل
actions|البس المريلة
places|باب المدرسة
concepts|واجب ناقص
food|عصير في الزمزمية
people|سواق الأتوبيس
actions|استنّى أختك
time|ميعاد الجرس
concepts|يوم دراسي
feelings|فرحان بصحابه`},
        {name: 'Sham el-Nessim', entries: `time|شم النسيم
objects|بيض ملوّن
food|رنجة
people|عيلة خارجة
actions|حضّر الفسحة
nature|هوا الربيع
food|فسيخ
objects|مفرش على النجيلة
places|جنينة عامة
actions|لوّن البيض
concepts|عادة مصرية
feelings|مبسوط بالجو
food|بصل وليمون
objects|شنطة أكل كبيرة
animals|كلب بلدي
actions|سيب المكان نضيف
nature|ورد متفتح
feelings|نايم تحت شجرة`},
        {name: 'A cold winter day', entries: `objects|بلوفر
food|شوربة عدس
people|واحد بردان
actions|اتغطّى كويس
time|ليلة شتا
feelings|نفسه يدفى
objects|شراب تقيل
food|بطاطا مشوية
nature|رعشة برد
actions|اقفل الهوا
places|ركن دافي
concepts|البرد دخل العضم
objects|لحاف تقيل
food|سحلب
actions|قرّب من الدفاية
nature|ميّه المطر
time|الصبح الساقع
feelings|دفيان ومرتاح`},
        {name: 'A summer power cut', entries: `time|عزّ الحر
technology|مروحة واقفة
objects|شباك مفتوح
actions|هوّي الأوضة
concepts|النور قطع
feelings|حرّان
technology|كشاف شحن
objects|ازازة ميّه ساقعة
people|جار معاه كشاف
actions|اطلع البلكونة
time|لحد ما النور ييجي
concepts|سهرانين سوا
food|حتة بطيخ
nature|نسمة بالليل
technology|النور رجع
actions|شغّل المروحة
concepts|حكاية في الضلمة
feelings|شمّة هوا`},
    ]},
];

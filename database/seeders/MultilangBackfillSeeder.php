<?php

namespace Database\Seeders;

use App\Models\AdminNotification;
use App\Models\Announcement;
use App\Models\FundraisingCampaign;
use App\Models\Merchant;
use App\Models\MerchantCategory;
use App\Models\PlatformBenefit;
use App\Models\RedemptionItem;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanDescription;
use Illuminate\Database\Seeder;

/**
 * Fills Bahasa Malaysia (_ms) and Chinese (_zh) columns for existing English rows.
 * Safe to run multiple times (only updates when ms/zh are empty or always overwrite — we overwrite for idempotent seeding).
 */
class MultilangBackfillSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedMerchants();
        $this->seedMerchantCategories();
        $this->seedRedemptionItems();
        $this->seedSubscriptionPlans();
        $this->seedFundraisingCampaigns();
        $this->seedPlatformBenefits();
        $this->seedAdminNotifications();
        $this->seedAnnouncements();

        $this->command->info('Multilang (ms/zh) backfill completed.');
    }

    private function seedMerchants(): void
    {
        $map = [
            'abc@merchant.test' => [
                'company_name_ms' => 'ABC Mart',
                'company_name_zh' => 'ABC 超市',
                'short_description_ms' => 'Kedai runcit kejiranan anda dengan pelbagai barangan keperluan harian.',
                'short_description_zh' => '您身边的邻里超市，提供各类日常必需品。',
                'about_description_ms' => 'ABC Mart telah lama menjadi pilihan kejiranan. Kami menumpukan pada hasilan segar, barangan dapur, dan keperluan rumah dengan perkhidmatan mesra dan harga berpatutan.',
                'about_description_zh' => 'ABC Mart 多年来深受邻里信赖。我们专注新鲜食材、粮油日用品与居家好物，价格公道、服务亲切。',
            ],
            'blue@merchant.test' => [
                'company_name_ms' => 'Blue Cafe',
                'company_name_zh' => '蓝咖啡馆',
                'short_description_ms' => 'Kopi khas dan snek ringan di pusat bandar JB.',
                'short_description_zh' => '新山市中心精品咖啡与轻食。',
                'about_description_ms' => 'Blue Cafe memanggang biji kopi kecil dan menyajikan brunch sepanjang hari di Johor Bahru. Berehat di dalam atau di teres kami, nikmati Wi‑Fi, dan tanya barista kami tentang campuran bermusim.',
                'about_description_zh' => 'Blue Cafe 在新山提供小批量烘焙咖啡与全日早午餐。可在室内或露台放松，使用无线网络，并向咖啡师了解当季豆与冲煮活动。',
            ],
        ];

        foreach ($map as $email => $fields) {
            Merchant::where('company_email', $email)->update($fields);
        }
    }

    private function seedMerchantCategories(): void
    {
        $translations = $this->merchantCategoryTranslations();

        foreach (MerchantCategory::query()->cursor() as $cat) {
            $t = $translations[$cat->name] ?? null;
            if ($t) {
                $cat->update(['name_ms' => $t['ms'], 'name_zh' => $t['zh']]);
            }
        }
    }

    /** @return array<string, array{ms: string, zh: string}> */
    private function merchantCategoryTranslations(): array
    {
        return [
            'Food & Beverage' => ['ms' => 'Makanan & Minuman', 'zh' => '餐饮'],
            'Restaurant & Dining' => ['ms' => 'Restoran & Santapan', 'zh' => '餐厅与餐饮'],
            'Café & Coffee Shop' => ['ms' => 'Kafe & Kedai Kopi', 'zh' => '咖啡馆'],
            'Bakery & Confectionery' => ['ms' => 'Bakeri & Kuih-muih', 'zh' => '烘焙与甜品'],
            'Fast Food & Takeaway' => ['ms' => 'Makanan Segera & Bungkus', 'zh' => '快餐与外带'],
            'Food Delivery' => ['ms' => 'Penghantaran Makanan', 'zh' => '外卖配送'],
            'Fashion & Apparel' => ['ms' => 'Fesyen & Pakaian', 'zh' => '时尚与服饰'],
            'Footwear & Accessories' => ['ms' => 'Kasut & Aksesori', 'zh' => '鞋履与配饰'],
            'Jewellery & Watches' => ['ms' => 'Barang Kemas & Jam Tangan', 'zh' => '珠宝与钟表'],
            'Electronics & Gadgets' => ['ms' => 'Elektronik & Gajet', 'zh' => '电子与数码'],
            'Home & Living' => ['ms' => 'Rumah & Gaya Hidup', 'zh' => '家居与生活'],
            'Furniture & Decor' => ['ms' => 'Perabot & Hiasan', 'zh' => '家具与装饰'],
            'Books & Stationery' => ['ms' => 'Buku & Alat Tulis', 'zh' => '图书与文具'],
            'Sports & Outdoors' => ['ms' => 'Sukan & Luar', 'zh' => '运动与户外'],
            'Toys & Games' => ['ms' => 'Mainan & Permainan', 'zh' => '玩具与游戏'],
            'Supermarket & Grocery' => ['ms' => 'Pasar Raya & Barangan Runcit', 'zh' => '超市与杂货'],
            'Convenience Store' => ['ms' => 'Kedai Runcit', 'zh' => '便利店'],
            'Pharmacy & Health Store' => ['ms' => 'Farmasi & Kesihatan', 'zh' => '药房与健康店'],
            'Beauty & Cosmetics' => ['ms' => 'Kecantikan & Kosmetik', 'zh' => '美妆'],
            'Hair Salon & Barbershop' => ['ms' => 'Salon Rambut & Tukang Cukur', 'zh' => '美发与理发'],
            'Spa & Massage' => ['ms' => 'Spa & Urutan', 'zh' => '水疗与按摩'],
            'Fitness & Gym' => ['ms' => 'Kecergasan & Gim', 'zh' => '健身'],
            'Health & Wellness' => ['ms' => 'Kesihatan & Kesejahteraan', 'zh' => '健康与养生'],
            'Optical' => ['ms' => 'Optik', 'zh' => '眼镜光学'],
            'Automotive & Workshop' => ['ms' => 'Automotif & Bengkel', 'zh' => '汽车与维修'],
            'Laundry & Dry Cleaning' => ['ms' => 'Dobi & Dobi Kering', 'zh' => '洗衣与干洗'],
            'Printing & Signage' => ['ms' => 'Percetakan & Papan Iklan', 'zh' => '印刷与招牌'],
            'Photography & Videography' => ['ms' => 'Fotografi & Videografi', 'zh' => '摄影与摄像'],
            'IT & Tech Services' => ['ms' => 'Perkhidmatan IT & Teknologi', 'zh' => 'IT 与技术服务'],
            'Legal & Accounting' => ['ms' => 'Guaman & Perakaunan', 'zh' => '法律与会计'],
            'Cleaning & Maintenance' => ['ms' => 'Pembersihan & Penyelenggaraan', 'zh' => '清洁与维护'],
            'Courier & Logistics' => ['ms' => 'Kurier & Logistik', 'zh' => '快递与物流'],
            'Education & Tuition' => ['ms' => 'Pendidikan & Tuisyen', 'zh' => '教育与补习'],
            'Skills & Vocational Training' => ['ms' => 'Kemahiran & Latihan Vokasional', 'zh' => '技能与职业培训'],
            'Language School' => ['ms' => 'Sekolah Bahasa', 'zh' => '语言学校'],
            'Clinic & Medical' => ['ms' => 'Klinik & Perubatan', 'zh' => '诊所与医疗'],
            'Dental' => ['ms' => 'Pergigian', 'zh' => '牙科'],
            'Veterinary' => ['ms' => 'Veterinar', 'zh' => '兽医'],
            'Traditional & Alternative Medicine' => ['ms' => 'Perubatan Tradisional & Alternatif', 'zh' => '传统与替代医学'],
            'Entertainment & Recreation' => ['ms' => 'Hiburan & Rekreasi', 'zh' => '娱乐与休闲'],
            'Travel & Tourism' => ['ms' => 'Pelancongan', 'zh' => '旅游'],
            'Hotel & Accommodation' => ['ms' => 'Hotel & Penginapan', 'zh' => '酒店与住宿'],
            'Event Planning' => ['ms' => 'Perancangan Acara', 'zh' => '活动策划'],
            'Art & Craft' => ['ms' => 'Seni & Kraftangan', 'zh' => '艺术与手工'],
            'Real Estate & Property' => ['ms' => 'Hartanah & Hartanah', 'zh' => '房地产'],
            'Construction & Renovation' => ['ms' => 'Pembinaan & Pengubahsuaian', 'zh' => '建筑与装修'],
            'Agriculture & Farming' => ['ms' => 'Pertanian & Penternakan', 'zh' => '农业与养殖'],
            'Nursery & Landscaping' => ['ms' => 'Tapak Semai & Landskap', 'zh' => '苗圃与园林'],
            'Manufacturing & Industrial' => ['ms' => 'Pembuatan & Industri', 'zh' => '制造与工业'],
            'Wholesale & Distribution' => ['ms' => 'Borong & Pengedaran', 'zh' => '批发与分销'],
            'Import & Export' => ['ms' => 'Import & Eksport', 'zh' => '进出口'],
            'Advertising & Marketing' => ['ms' => 'Pengiklanan & Pemasaran', 'zh' => '广告与营销'],
            'Design & Creative Agency' => ['ms' => 'Reka Bentuk & Agensi Kreatif', 'zh' => '设计与创意机构'],
            'Media & Broadcasting' => ['ms' => 'Media & Penyiaran', 'zh' => '媒体与广播'],
            'Non-Profit & Social Enterprise' => ['ms' => 'Bukan Untung & Keusahawanan Sosial', 'zh' => '非营利与社会企业'],
            'Religious Organisation' => ['ms' => 'Organisasi Agama', 'zh' => '宗教组织'],
            'Community & Association' => ['ms' => 'Komuniti & Persatuan', 'zh' => '社区与协会'],
            'Others' => ['ms' => 'Lain-lain', 'zh' => '其他'],
            'Retail' => ['ms' => 'Runcit', 'zh' => '零售'],
        ];
    }

    private function seedRedemptionItems(): void
    {
        $map = [
            'Shopping Voucher RM50' => [
                'name_ms' => 'Baucar Membeli-belah RM50',
                'name_zh' => 'RM50 购物礼券',
                'description_ms' => 'Tebus baucar RM50 yang sah di semua peniaga berpartisipasi.',
                'description_zh' => '可兑换 RM50 购物礼券，适用于所有参与商户。',
            ],
            'Free Coffee' => [
                'name_ms' => 'Kopi Percuma',
                'name_zh' => '免费咖啡',
                'description_ms' => 'Satu cawan kopi percuma pilihan anda di Blue Cafe.',
                'description_zh' => '在 Blue Cafe 任选一杯免费咖啡。',
            ],
            'RM20 Cash Rebate' => [
                'name_ms' => 'Rebat Tunai RM20',
                'name_zh' => 'RM20 现金回扣',
                'description_ms' => 'Rebat tunai RM20 dikreditkan ke akaun anda.',
                'description_zh' => 'RM20 现金回扣将存入您的账户。',
            ],
        ];

        foreach ($map as $name => $fields) {
            RedemptionItem::where('name', $name)->update($fields);
        }
    }

    private function seedSubscriptionPlans(): void
    {
        $plans = [
            'Member Monthly' => ['name_ms' => 'Ahli Bulanan', 'name_zh' => '会员月付'],
            'Member Yearly' => ['name_ms' => 'Ahli Tahunan', 'name_zh' => '会员年付'],
            'Merchant Pro' => ['name_ms' => 'Peniaga Pro', 'name_zh' => '商家专业版'],
        ];

        foreach ($plans as $name => $fields) {
            SubscriptionPlan::where('name', $name)->update($fields);
        }

        $descMap = [
            'Earn points at all participating merchants' => [
                'description_ms' => 'Dapatkan mata di semua peniaga berpartisipasi',
                'description_zh' => '在所有参与商户赚取积分',
            ],
            'Access to member exclusive deals' => [
                'description_ms' => 'Akses tawaran eksklusif ahli',
                'description_zh' => '专享会员优惠',
            ],
            'Digital membership card with QR code' => [
                'description_ms' => 'Kad keahlian digital dengan kod QR',
                'description_zh' => '带二维码的电子会员卡',
            ],
            'Everything in Member Monthly' => [
                'description_ms' => 'Semua faedah dalam Ahli Bulanan',
                'description_zh' => '包含月付会员全部权益',
            ],
            'Save 17% with annual billing' => [
                'description_ms' => 'Jimat 17% dengan bil tahunan',
                'description_zh' => '年付节省 17%',
            ],
            'Priority customer support' => [
                'description_ms' => 'Sokongan pelanggan keutamaan',
                'description_zh' => '优先客服支持',
            ],
            'Grant points to members via QR scan' => [
                'description_ms' => 'Berikan mata kepada ahli melalui imbas QR',
                'description_zh' => '通过扫码向会员发放积分',
            ],
            'Business analytics dashboard' => [
                'description_ms' => 'Papan pemuka analitik perniagaan',
                'description_zh' => '商业分析仪表板',
            ],
            'Listed in member-facing merchant directory' => [
                'description_ms' => 'Disenaraikan dalam direktori peniaga untuk ahli',
                'description_zh' => '收录于会员端商户目录',
            ],
        ];

        foreach ($descMap as $en => $fields) {
            SubscriptionPlanDescription::where('description', $en)->update($fields);
        }
    }

    private function seedFundraisingCampaigns(): void
    {
        $campaigns = [
            'Education for Every Child' => [
                'campaign_name_ms' => 'Pendidikan untuk Setiap Kanak-kanak',
                'campaign_name_zh' => '让每个孩子受教育',
                'organization_name_ms' => 'UNICEF Malaysia',
                'organization_name_zh' => '联合国儿童基金会马来西亚',
                'description_ms' => 'Bantu menyediakan alat tulis, buku dan bahan pembelajaran kepada kanak-kanak kurang berkemampuan di luar bandar Malaysia.',
                'description_zh' => '为马来西亚偏远地区的贫困儿童提供文具、课本与学习用品。',
                'sub_description_ms' => 'Setiap ringgit sampai kepada kanak-kanak yang benar-benar memerlukan.',
                'sub_description_zh' => '每一分钱都送到真正有需要的孩子手中。',
                'impact_ms' => 'RM10 membiayai alat tulis untuk 1 kanak-kanak',
                'impact_zh' => 'RM10 可资助一名儿童的文具',
                'how_it_helps_ms' => [
                    'Menyediakan kit alat tulis untuk pelajar luar bandar',
                    'Mengedarkan buku rujukan selaras dengan kurikulum kebangsaan',
                    'Menyokong pelajar berisiko berhenti sekolah',
                    'Menampung beg sekolah dan alat pembelajaran asas',
                ],
                'how_it_helps_zh' => [
                    '为乡村学生提供文具套装',
                    '分发与国家课程配套的参考书',
                    '帮助有辍学风险的学生',
                    '提供书包与基本学习用品',
                ],
            ],
            'Feed Families in Need' => [
                'campaign_name_ms' => 'Suap Keluarga Memerlukan',
                'campaign_name_zh' => '为困难家庭送粮',
                'organization_name_ms' => 'Pertubuhan Kebajikan Food Aid',
                'organization_name_zh' => '食品援助福利组织',
                'description_ms' => 'Agihkan bakul makanan dan hidangan harian kepada keluarga berpendapatan rendah dan ibu tunggal.',
                'description_zh' => '向低收入家庭与单亲妈妈分发食品篮与每日餐食。',
                'sub_description_ms' => 'Kami agih makanan dengan maruah.',
                'sub_description_zh' => '有尊严地分发食物。',
                'impact_ms' => 'RM20 menyediakan 5 hidangan untuk satu keluarga',
                'impact_zh' => 'RM20 可为一家提供 5 餐',
                'how_it_helps_ms' => [
                    'Penghantaran bakul makanan bulanan ke isi rumah B40',
                    'Sayur segar dan barang kering termasuk',
                    'Mengutamakan ibu tunggal dan warga emas',
                    'Pengedaran melalui rakan komuniti dipercayai',
                ],
                'how_it_helps_zh' => [
                    '每月向 B40 家庭配送食品篮',
                    '包含新鲜蔬果与干粮',
                    '优先单亲与长者家庭',
                    '通过社区伙伴分发',
                ],
            ],
            'Healthcare Support Fund' => [
                'campaign_name_ms' => 'Dana Sokongan Kesihatan',
                'campaign_name_zh' => '医疗援助基金',
                'organization_name_ms' => 'Inisiatif Pembiayaan Hospital',
                'organization_name_zh' => '医院资助计划',
                'description_ms' => 'Tampung kos perubatan dan bekalkan ubat kepada pesakit yang tidak mampu di hospital kerajaan.',
                'description_zh' => '为无力支付公立医疗费用的患者提供医药费与基本药物。',
                'sub_description_ms' => 'Merapatkan jurang untuk pesakit yang paling memerlukan.',
                'sub_description_zh' => '为最需要的患者架起桥梁。',
                'impact_ms' => 'RM50 menampung ubat asas selama 1 minggu',
                'impact_zh' => 'RM50 可支付一周基本药物',
                'how_it_helps_ms' => [
                    'Membayar balik kos ubat penting',
                    'Membiayai alat bantu mobiliti dan peranti perubatan',
                    'Menampung pengangkutan temujanji pesakit luar',
                    'Menyokong pesakit kronik jangka panjang',
                ],
                'how_it_helps_zh' => [
                    '报销必需药物费用',
                    '资助行动辅具与医疗设备',
                    '补贴门诊交通费用',
                    '支持长期慢性病患者',
                ],
            ],
            'Plant Trees, Save Rivers' => [
                'campaign_name_ms' => 'Tanam Pokok, Selamatkan Sungai',
                'campaign_name_zh' => '植树护河',
                'organization_name_ms' => 'WWF Malaysia',
                'organization_name_zh' => '世界自然基金会马来西亚',
                'description_ms' => 'Pulihkan tanah hutan yang ditebang dan sungai yang tercemar melalui penanaman pokok dan pemulihan sungai.',
                'description_zh' => '通过植树与河流修复，恢复遭砍伐的土地与被污染的河流。',
                'sub_description_ms' => 'Membantu alam semula jadi pulih — satu pokok, satu sungai pada satu masa.',
                'sub_description_zh' => '帮助自然恢复——一次一棵树、一条河。',
                'impact_ms' => 'RM30 menanam 3 pokok atas nama anda',
                'impact_zh' => 'RM30 可为您冠名种植 3 棵树',
                'how_it_helps_ms' => [
                    'Spesies pokok asli ditanam di kawasan ditebang',
                    'Pembersihan sungai di zon tadahan utama',
                    'Melindungi habitat hidupan liar terancam',
                    'Pendidikan komuniti tentang amalan lestari',
                ],
                'how_it_helps_zh' => [
                    '在砍伐区种植本土树种',
                    '主要集水区河道清理',
                    '保护濒危物种栖息地',
                    '社区可持续教育',
                ],
            ],
            'Elderly Care & Dignity' => [
                'campaign_name_ms' => 'Penjagaan & Maruah Warga Emas',
                'campaign_name_zh' => '长者关怀与尊严',
                'organization_name_ms' => 'Rumah Seri Kenangan',
                'organization_name_zh' => '回忆之家安老院',
                'description_ms' => 'Sokong warga emas di rumah jagaan dengan membiayai keperluan harian, penjagaan perubatan dan aktiviti riadah.',
                'description_zh' => '资助养老院长者的日常所需、医疗与康乐活动。',
                'sub_description_ms' => 'Meningkatkan kualiti tahun emas mereka.',
                'sub_description_zh' => '提升他们的晚年生活质量。',
                'impact_ms' => 'RM25 membiayai 1 hari penjagaan warga emas',
                'impact_zh' => 'RM25 可资助一日长者照护',
                'how_it_helps_ms' => [
                    'Makanan berkhasiat harian untuk penghuni',
                    'Pemeriksaan kesihatan dan fisioterapi bulanan',
                    'Aktiviti riadah dan kesihatan mental',
                    'Barangan penjagaan peribadi',
                ],
                'how_it_helps_zh' => [
                    '住户每日营养餐食',
                    '每月体检与理疗',
                    '康乐与心理健康活动',
                    '个人护理用品',
                ],
            ],
            'Community Relief 2026' => [
                'campaign_name_ms' => 'Bantuan Komuniti 2026',
                'campaign_name_zh' => '2026 社区援助',
                'organization_name_ms' => null,
                'organization_name_zh' => null,
                'description_ms' => 'Menyokong kebajikan pekerja dan inisiatif komuniti di seluruh Malaysia.',
                'description_zh' => '支持马来西亚各地的工人福利与社区倡议。',
                'sub_description_ms' => null,
                'sub_description_zh' => null,
                'impact_ms' => null,
                'impact_zh' => null,
                'how_it_helps_ms' => null,
                'how_it_helps_zh' => null,
            ],
        ];

        foreach ($campaigns as $campaignName => $fields) {
            $row = FundraisingCampaign::where('campaign_name', $campaignName)->first();
            if (! $row) {
                continue;
            }

            $update = array_filter([
                'campaign_name_ms' => $fields['campaign_name_ms'] ?? null,
                'campaign_name_zh' => $fields['campaign_name_zh'] ?? null,
                'organization_name_ms' => $fields['organization_name_ms'] ?? null,
                'organization_name_zh' => $fields['organization_name_zh'] ?? null,
                'description_ms' => $fields['description_ms'] ?? null,
                'description_zh' => $fields['description_zh'] ?? null,
                'sub_description_ms' => $fields['sub_description_ms'] ?? null,
                'sub_description_zh' => $fields['sub_description_zh'] ?? null,
                'impact_ms' => $fields['impact_ms'] ?? null,
                'impact_zh' => $fields['impact_zh'] ?? null,
                'how_it_helps_ms' => $fields['how_it_helps_ms'] ?? null,
                'how_it_helps_zh' => $fields['how_it_helps_zh'] ?? null,
            ], fn ($v) => $v !== null);

            if ($update !== []) {
                $row->update($update);
            }
        }
    }

    private function seedPlatformBenefits(): void
    {
        $map = [
            'Reward Every Transaction' => [
                'title_ms' => 'Ganjaran Setiap Transaksi',
                'title_zh' => '每笔交易都有奖励',
                'content_ms' => 'Ahli memperoleh mata daripada pembelian layak dan boleh menebus baucar, hadiah dan ganjaran khas terus dalam aplikasi.',
                'content_zh' => '会员在符合条件的消费中赚取积分，并可在应用内兑换礼券、礼品与活动奖励。',
            ],
            'Business Growth for Merchants' => [
                'title_ms' => 'Pertumbuhan Perniagaan untuk Peniaga',
                'title_zh' => '助力商家增长',
                'content_ms' => 'Peniaga boleh meningkatkan kunjungan ulangan, menjalankan kempen sasaran dan menjejak penglibatan pelanggan.',
                'content_zh' => '商家可提高复购、开展定向活动，并通过积分体系追踪客户互动。',
            ],
            'Secure and Seamless Experience' => [
                'title_ms' => 'Pengalaman Selamat dan Lancar',
                'title_zh' => '安全顺畅的体验',
                'content_ms' => 'Platform menyokong log masuk selamat, notifikasi terurus dan rekod aktiviti yang telus.',
                'content_zh' => '平台支持安全登录、可管理的通知与透明的活动记录。',
            ],
        ];

        foreach ($map as $title => $fields) {
            PlatformBenefit::where('title', $title)->update($fields);
        }
    }

    private function seedAdminNotifications(): void
    {
        $map = [
            'Welcome to PKPMS!' => [
                'title_ms' => 'Selamat datang ke PKPMS!',
                'title_zh' => '欢迎使用 PKPMS！',
                'message_ms' => 'Terima kasih kerana menyertai PKPMS. Mula kumpul mata dengan melawat peniaga berpartisipasi.',
                'message_zh' => '感谢加入 PKPMS。前往参与商户即可开始赚取积分。',
            ],
            'Double Points Weekend Active' => [
                'title_ms' => 'Poin Berganda Hujung Minggu Aktif',
                'title_zh' => '周末双倍积分进行中',
                'message_ms' => 'Poin berganda kini aktif hujung minggu ini. Lawati mana-mana peniaga untuk memperoleh 2x mata!',
                'message_zh' => '本周末双倍积分已开启。前往任意商户消费即可获得双倍积分！',
            ],
            'New Redemption Items Available' => [
                'title_ms' => 'Item Penebusan Baharu Tersedia',
                'title_zh' => '上新兑换礼品',
                'message_ms' => 'Lihat katalog penebusan baharu — Kopi Percuma kini tersedia dengan hanya 100 mata!',
                'message_zh' => '查看全新兑换目录——免费咖啡仅需 100 积分！',
            ],
            'Subscription Reminder' => [
                'title_ms' => 'Peringatan Langganan',
                'title_zh' => '订阅提醒',
                'message_ms' => 'Langganan peniaga anda akan tamat dalam 3 hari. Perbaharui sekarang untuk terus memberi mata.',
                'message_zh' => '您的商家订阅将在 3 天后到期。请续订以继续发放积分。',
            ],
        ];

        foreach ($map as $title => $fields) {
            AdminNotification::where('title', $title)->update($fields);
        }
    }

    private function seedAnnouncements(): void
    {
        // DemoData-style short announcements
        $short = [
            'Double Points Weekend' => [
                'title_ms' => 'Poin Berganda Hujung Minggu',
                'title_zh' => '周末双倍积分',
                'content_ms' => 'Dapatkan poin berganda hujung minggu ini di semua peniaga berpartisipasi! Sah pada Sabtu dan Ahad sahaja.',
                'content_zh' => '本周末在所有参与商户赚取双倍积分！仅限周六与周日。',
                'terms_and_conditions_ms' => 'Tawaran sah pada hujung minggu sahaja. Tidak boleh digabung dengan promosi lain.',
                'terms_and_conditions_zh' => '优惠仅限周末。不可与其他促销同时使用。',
            ],
            'New Merchants Joined PKPMS' => [
                'title_ms' => 'Peniaga Baharu Menyertai PKPMS',
                'title_zh' => '新商户加入 PKPMS',
                'content_ms' => 'Selamat datang peniaga terbaharu! Mula kumpul mata di ABC Mart dan Blue Cafe hari ini.',
                'content_zh' => '欢迎新商户！今日起在 ABC Mart 与 Blue Cafe 开始赚取积分。',
                'terms_and_conditions_ms' => null,
                'terms_and_conditions_zh' => null,
            ],
            'Ramadan Special Campaign' => [
                'title_ms' => 'Kempen Istimewa Ramadan',
                'title_zh' => '斋月特别活动',
                'content_ms' => 'Raikan Ramadan dengan ganjaran eksklusif. Dapatkan 1.5x mata di peniaga Makanan & Minuman sepanjang bulan.',
                'content_zh' => '斋月专享奖励。整月在餐饮类商户赚取 1.5 倍积分。',
                'terms_and_conditions_ms' => 'Sah di peniaga Makanan & Minuman berpartisipasi sahaja.',
                'terms_and_conditions_zh' => '仅限参与餐饮商户。',
            ],
        ];

        foreach ($short as $title => $fields) {
            $payload = array_filter([
                'title_ms' => $fields['title_ms'] ?? null,
                'title_zh' => $fields['title_zh'] ?? null,
                'content_ms' => $fields['content_ms'] ?? null,
                'content_zh' => $fields['content_zh'] ?? null,
                'terms_and_conditions_ms' => $fields['terms_and_conditions_ms'] ?? null,
                'terms_and_conditions_zh' => $fields['terms_and_conditions_zh'] ?? null,
            ], fn ($v) => $v !== null);
            if ($payload !== []) {
                Announcement::where('title', $title)->update($payload);
            }
        }

        // AnnouncementSeeder long-form (match by title)
        $long = [
            'New Members Welcome Bonus' => [
                'title_ms' => 'Bonus Selamat Datang Ahli Baharu',
                'title_zh' => '新会员欢迎奖励',
                'content_ms' => "Baharu di PKPMS? Selamat datang! Sebagai ahli baharu, anda menerima 500 mata bonus serta-merta selepas transaksi pertama yang layak.\n\nGunakan mata untuk menebus tawaran eksklusif daripada rakan peniaga kami.",
                'content_zh' => "刚加入 PKPMS？欢迎！新会员在完成首笔符合条件的交易后，将立即获得 500 奖励积分。\n\n可用积分兑换合作商户的专属好礼。",
                'terms_and_conditions_ms' => 'Bonus 500 mata untuk ahli baharu sahaja. Dikreditkan selepas transaksi pertama yang layak.',
                'terms_and_conditions_zh' => '欢迎奖励 500 积分仅限新会员。于首笔符合条件的交易后发放。',
            ],
            'Refer a Friend, Earn Together' => [
                'title_ms' => 'Rujuk Rakan, Raup Ganjaran Bersama',
                'title_zh' => '推荐好友，一起赚积分',
                'content_ms' => "Jemput rakan menyertai PKPMS dan anda berdua boleh memperoleh 200 mata bonus apabila rakan menyelesaikan transaksi pertama yang layak.\n\nKongsi kod rujukan dari skrin Kad Ahli anda.",
                'content_zh' => "邀请好友加入 PKPMS，当好友完成首笔符合条件的交易后，双方各得 200 奖励积分。\n\n在会员卡页面分享您的推荐码。",
                'terms_and_conditions_ms' => 'Bonus 200 mata dikreditkan kepada perujuk dan dirujuk selepas transaksi pertama dirujuk selesai.',
                'terms_and_conditions_zh' => '推荐人与被推荐人各获 200 积分，于被推荐人完成首笔符合条件的交易后发放。',
            ],
        ];

        foreach ($long as $title => $fields) {
            Announcement::where('title', $title)->update($fields);
        }

        // "Double Points Weekend" from AnnouncementSeeder has longer body — update if this row exists with long content
        $doubleWeekendLong = Announcement::where('title', 'Double Points Weekend')->first();
        if ($doubleWeekendLong && str_contains((string) $doubleWeekendLong->content, 'This weekend only')) {
            $doubleWeekendLong->update([
                'title_ms' => 'Poin Berganda Hujung Minggu',
                'title_zh' => '周末双倍积分',
                'content_ms' => "Hujung minggu ini sahaja, peroleh Poin BERGANDA untuk setiap pembelian di semua peniaga PKPMS yang mengambil bahagian!\n\nSama ada anda makan di luar, membeli-belah fesyen atau penjagaan diri — setiap ringgit yang dibelanjakan memperoleh 2× mata seperti biasa secara automatik.\n\nLog masuk ke aplikasi dan semak direktori peniaga untuk mencari cawangan berhampiran anda.",
                'content_zh' => "本周末限定：在所有参与 PKPMS 的商户消费，每笔消费均可获得双倍积分！\n\n无论外出用餐、时尚购物或健康服务——每消费一令吉自动获得 2 倍积分。\n\n登录应用并查看商户目录，找到您附近的参与门店。",
                'terms_and_conditions_ms' => 'Sah pada Sabtu dan Ahad sahaja. Poin berganda untuk pembelian layak di peniaga berpartisipasi. Tidak boleh digabung dengan tawaran promosi lain.',
                'terms_and_conditions_zh' => '仅限周六与周日。双倍积分适用于参与商户的符合条件消费。不可与其他促销同时使用。',
            ]);
        }
    }
}

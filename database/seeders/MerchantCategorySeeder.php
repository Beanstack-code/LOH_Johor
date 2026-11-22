<?php

namespace Database\Seeders;

use App\Models\MerchantCategory;
use Illuminate\Database\Seeder;

class MerchantCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            // Food & Beverage
            ['name' => 'Food & Beverage', 'name_ms' => 'Makanan & Minuman', 'name_zh' => '食物与饮料'],
            ['name' => 'Restaurant & Dining', 'name_ms' => 'Restoran & Santapan', 'name_zh' => '餐厅与用餐'],
            ['name' => 'Café & Coffee Shop', 'name_ms' => 'Kafe & Kedai Kopi', 'name_zh' => '咖啡馆与咖啡店'],
            ['name' => 'Bakery & Confectionery', 'name_ms' => 'Bakeri & Kuih-muih', 'name_zh' => '面包店与糕点'],
            ['name' => 'Fast Food & Takeaway', 'name_ms' => 'Makanan Segera & Bungkus', 'name_zh' => '快餐与外带'],
            ['name' => 'Food Delivery', 'name_ms' => 'Penghantaran Makanan', 'name_zh' => '送餐服务'],

            // Retail
            ['name' => 'Fashion & Apparel', 'name_ms' => 'Fesyen & Pakaian', 'name_zh' => '时尚与服装'],
            ['name' => 'Footwear & Accessories', 'name_ms' => 'Kasut & Aksesori', 'name_zh' => '鞋类与配饰'],
            ['name' => 'Jewellery & Watches', 'name_ms' => 'Barang Kemas & Jam', 'name_zh' => '珠宝与钟表'],
            ['name' => 'Electronics & Gadgets', 'name_ms' => 'Elektronik & Gadget', 'name_zh' => '电子与数码产品'],
            ['name' => 'Home & Living', 'name_ms' => 'Rumah & Gaya Hidup', 'name_zh' => '家居与生活'],
            ['name' => 'Furniture & Decor', 'name_ms' => 'Perabot & Hiasan', 'name_zh' => '家具与装饰'],
            ['name' => 'Books & Stationery', 'name_ms' => 'Buku & Alat Tulis', 'name_zh' => '图书与文具'],
            ['name' => 'Sports & Outdoors', 'name_ms' => 'Sukan & Aktiviti Luar', 'name_zh' => '运动与户外'],
            ['name' => 'Toys & Games', 'name_ms' => 'Mainan & Permainan', 'name_zh' => '玩具与游戏'],
            ['name' => 'Supermarket & Grocery', 'name_ms' => 'Pasar Raya & Barangan Runcit', 'name_zh' => '超市与杂货'],
            ['name' => 'Convenience Store', 'name_ms' => 'Kedai Runcit', 'name_zh' => '便利店'],
            ['name' => 'Pharmacy & Health Store', 'name_ms' => 'Farmasi & Kedai Kesihatan', 'name_zh' => '药房与健康用品店'],

            // Beauty & Wellness
            ['name' => 'Beauty & Cosmetics', 'name_ms' => 'Kecantikan & Kosmetik', 'name_zh' => '美容与化妆品'],
            ['name' => 'Hair Salon & Barbershop', 'name_ms' => 'Salon Rambut & Tukang Gunting', 'name_zh' => '美发沙龙与理发店'],
            ['name' => 'Spa & Massage', 'name_ms' => 'Spa & Urutan', 'name_zh' => '水疗与按摩'],
            ['name' => 'Fitness & Gym', 'name_ms' => 'Kecergasan & Gimnasium', 'name_zh' => '健身与健身房'],
            ['name' => 'Health & Wellness', 'name_ms' => 'Kesihatan & Kesejahteraan', 'name_zh' => '健康与养生'],
            ['name' => 'Optical', 'name_ms' => 'Optik', 'name_zh' => '眼镜与光学'],

            // Services
            ['name' => 'Automotive & Workshop', 'name_ms' => 'Automotif & Bengkel', 'name_zh' => '汽车与维修工坊'],
            ['name' => 'Laundry & Dry Cleaning', 'name_ms' => 'Dobi & Dry Cleaning', 'name_zh' => '洗衣与干洗'],
            ['name' => 'Printing & Signage', 'name_ms' => 'Percetakan & Papan Tanda', 'name_zh' => '印刷与招牌'],
            ['name' => 'Photography & Videography', 'name_ms' => 'Fotografi & Videografi', 'name_zh' => '摄影与摄像'],
            ['name' => 'IT & Tech Services', 'name_ms' => 'Perkhidmatan IT & Teknologi', 'name_zh' => 'IT与科技服务'],
            ['name' => 'Legal & Accounting', 'name_ms' => 'Undang-undang & Perakaunan', 'name_zh' => '法律与会计'],
            ['name' => 'Cleaning & Maintenance', 'name_ms' => 'Pembersihan & Penyelenggaraan', 'name_zh' => '清洁与维护'],
            ['name' => 'Courier & Logistics', 'name_ms' => 'Kurier & Logistik', 'name_zh' => '快递与物流'],

            // Education & Training
            ['name' => 'Education & Tuition', 'name_ms' => 'Pendidikan & Tuisyen', 'name_zh' => '教育与补习'],
            ['name' => 'Skills & Vocational Training', 'name_ms' => 'Kemahiran & Latihan Vokasional', 'name_zh' => '技能与职业培训'],
            ['name' => 'Language School', 'name_ms' => 'Sekolah Bahasa', 'name_zh' => '语言学校'],

            // Healthcare
            ['name' => 'Clinic & Medical', 'name_ms' => 'Klinik & Perubatan', 'name_zh' => '诊所与医疗'],
            ['name' => 'Dental', 'name_ms' => 'Pergigian', 'name_zh' => '牙科'],
            ['name' => 'Veterinary', 'name_ms' => 'Veterinar', 'name_zh' => '兽医'],
            ['name' => 'Traditional & Alternative Medicine', 'name_ms' => 'Perubatan Tradisional & Alternatif', 'name_zh' => '传统与替代医学'],

            // Entertainment & Lifestyle
            ['name' => 'Entertainment & Recreation', 'name_ms' => 'Hiburan & Rekreasi', 'name_zh' => '娱乐与休闲'],
            ['name' => 'Travel & Tourism', 'name_ms' => 'Pelancongan & Pelancong', 'name_zh' => '旅游'],
            ['name' => 'Hotel & Accommodation', 'name_ms' => 'Hotel & Penginapan', 'name_zh' => '酒店与住宿'],
            ['name' => 'Event Planning', 'name_ms' => 'Perancangan Acara', 'name_zh' => '活动策划'],
            ['name' => 'Art & Craft', 'name_ms' => 'Seni & Kraftangan', 'name_zh' => '艺术与手工艺'],

            // Real Estate & Property
            ['name' => 'Real Estate & Property', 'name_ms' => 'Hartanah & Harta', 'name_zh' => '房地产'],
            ['name' => 'Construction & Renovation', 'name_ms' => 'Pembinaan & Pengubahsuaian', 'name_zh' => '建筑与装修'],

            // Agriculture & Environment
            ['name' => 'Agriculture & Farming', 'name_ms' => 'Pertanian & Penternakan', 'name_zh' => '农业与养殖'],
            ['name' => 'Nursery & Landscaping', 'name_ms' => 'Nurseri & Landskap', 'name_zh' => '苗圃与景观'],

            // Manufacturing & Trade
            ['name' => 'Manufacturing & Industrial', 'name_ms' => 'Pembuatan & Industri', 'name_zh' => '制造业与工业'],
            ['name' => 'Wholesale & Distribution', 'name_ms' => 'Borong & Pengedaran', 'name_zh' => '批发与分销'],
            ['name' => 'Import & Export', 'name_ms' => 'Import & Eksport', 'name_zh' => '进出口'],

            // Media & Creative
            ['name' => 'Advertising & Marketing', 'name_ms' => 'Pengiklanan & Pemasaran', 'name_zh' => '广告与营销'],
            ['name' => 'Design & Creative Agency', 'name_ms' => 'Reka Bentuk & Agensi Kreatif', 'name_zh' => '设计与创意机构'],
            ['name' => 'Media & Broadcasting', 'name_ms' => 'Media & Penyiaran', 'name_zh' => '媒体与广播'],

            // Non-Profit & Community
            ['name' => 'Non-Profit & Social Enterprise', 'name_ms' => 'Bukan Untung & Keusahawanan Sosial', 'name_zh' => '非营利与社会企业'],
            ['name' => 'Religious Organisation', 'name_ms' => 'Organisasi Agama', 'name_zh' => '宗教组织'],
            ['name' => 'Community & Association', 'name_ms' => 'Komuniti & Persatuan', 'name_zh' => '社区与协会'],

            // Others
            ['name' => 'Others', 'name_ms' => 'Lain-lain', 'name_zh' => '其他'],
        ];

        foreach ($categories as $row) {
            MerchantCategory::updateOrCreate(
                ['name' => $row['name']],
                [
                    'name_ms' => $row['name_ms'],
                    'name_zh' => $row['name_zh'],
                    'is_active' => true,
                ]
            );
        }
    }
}

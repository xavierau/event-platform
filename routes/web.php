<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Admin\SiteSettingController;
use App\Http\Controllers\Admin\VenueController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\EditorUploadController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DevController;
use App\Http\Controllers\Admin\TagController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\EventOccurrenceController;
use App\Http\Controllers\Admin\TicketDefinitionController;
use App\Http\Controllers\Public\HomeController;
use App\Services\CategoryService;
use App\Actions\Categories\UpsertCategoryAction;

use App\Http\Controllers\Public\EventController as PublicEventController;

Route::get('/', HomeController::class)->name('home');
// Route::get('/', function () {

//     $service = new CategoryService(new UpsertCategoryAction());
//     $categories = $service->getPublicCategories();

//     dd($categories);
// })->name('home');

// Route for Public Event Detail Page
Route::get('/events/{event}', function ($eventId) {


    // In a real application, you would fetch event data from the database using $eventId
    // For now, we'll use placeholder data similar to what's in EventDetail.vue
    // This should be handled by a dedicated controller, e.g., PublicEventController@show
    $placeholderEvent = [
        'id' => $eventId,
        'name' => '【深圳】【徐佳莹】「变得有些奢侈的事」巡回演唱会-深圳站', // Example name for a specific occurrence focused event
        'category_tag' => '演唱会',
        // 'date_range' => '2025.06.14-06.28', // General date range removed, derived from occurrences
        'duration_info' => '约120分钟 (以现场为准)',
        'price_range' => '¥380-1380',
        'discount_info' => null, // No discount in example
        'venue_name' => '深圳湾体育中心"春茧"体育馆', // This would be for the selected occurrence if it varies
        'venue_address' => '深圳市南山区滨海大道3001号', // Ditto
        'tags' => ['重要通知', '条件退', '实名制购票和入场', '不支持选座', '电子票'],
        'rating' => 9.5, // Example rating
        'rating_count' => 1200,
        'reviews_summary' => '演唱会很精彩，LALA的歌声太棒了！还会再来！氛围感十足...',
        'review_highlight_link_text' => '查看全部评价 >',
        'want_to_see_count' => 10500,
        'main_poster_url' => 'https://via.placeholder.com/1080x720.png?text=Event+Poster+' . $eventId,
        'thumbnail_url' => 'https://via.placeholder.com/300x400.png?text=LALA+Concert+' . $eventId,
        'description_html' => '<p><strong>演出详情...</strong></p><p>这里是徐佳莹演唱会的详细介绍... 富文本内容。</p>',
        'occurrences' => [
            [
                'id' => 'shanghai',
                'name' => '上海站',
                'date_short' => '06.14',
                'full_date_time' => '2025.06.14 周五 19:30',
                'status_tag' => '预约'
            ],
            [
                'id' => 'shenzhen',
                'name' => '深圳站',
                'date_short' => '06.21',
                'full_date_time' => '2025.06.21 周六 19:00',
                'status_tag' => '预约'
            ],
            [
                'id' => 'beijing',
                'name' => '北京站',
                'date_short' => '06.28',
                'full_date_time' => '2025.06.28 周五 20:00',
                'status_tag' => '预约'
            ],
            [
                'id' => 'guangzhou',
                'name' => '广州站',
                'date_short' => '07.05',
                'full_date_time' => '2025.07.05 周五 19:30',
                'status_tag' => '开售提醒'
            ],
            [
                'id' => 'chengdu',
                'name' => '成都站',
                'date_short' => '07.12',
                'full_date_time' => '2025.07.12 周五 19:00',
                'status_tag' => '即将开售'
            ]
        ]
    ];

    $placeholderEvent = [
        'id' => '123',
        'name' => '【北京】【大笑喜剧】三里屯脱口秀爆笑解压专场|周一至周日吐槽爆笑大会|开心互动即兴喜剧之夜精品秀',
        'category_tag' => '脱口秀',
        'duration_info' => '约90分钟 (以现场为准)',
        'price_range' => '¥60-360',
        'discount_info' => '2人套票 | 6.5折',
        'main_poster_url' => 'https://via.placeholder.com/1080x1500.png?text=Event+Main+Poster',
        'thumbnail_url' => 'https://via.placeholder.com/150x200.png?text=Thumb',
        'description_html' => '<p><strong>【大笑喜剧】精品单口喜剧 | 开心 | 吐槽 | 爆梗专场</strong></p><p>演出介绍内容...这里会是富文本HTML...</p>',
        'venue_name' => '北京·大笑脱口秀俱乐部-三里屯店',
        'venue_address' => '北京市朝阳区工人体育场北路凯富大厦三层',
        'occurrences' => [
            [
                'id' => 'occ1',
                'name' => '上海站',
                'date_short' => '06.14',
                'full_date_time' => '2025.06.14 周五 19:30',
                'status_tag' => '预约',
                'venue_name' => '上海·大笑脱口秀俱乐部-静安店',
                'venue_address' => '上海市静安区南京西路1788号',
                'tickets' => [
                    [
                        'id' => 'ticket1',
                        'name' => 'VIP座',
                        'description' => '前排最佳观看位置，含签名海报',
                        'price' => 360,
                        'quantity_available' => 50
                    ],
                    [
                        'id' => 'ticket2',
                        'name' => '普通座',
                        'description' => '中排标准座位',
                        'price' => 180,
                        'quantity_available' => 100
                    ],
                    [
                        'id' => 'ticket3',
                        'name' => '学生票',
                        'description' => '需持有效学生证',
                        'price' => 60,
                        'quantity_available' => 50
                    ]
                ]
            ],
            [
                'id' => 'occ2',
                'name' => '深圳站',
                'date_short' => '06.21',
                'full_date_time' => '2025.06.21 周六 19:00',
                'status_tag' => '预约',
                'venue_name' => '深圳·大笑脱口秀俱乐部-福田店',
                'venue_address' => '深圳市福田区福华三路',
                'tickets' => [
                    [
                        'id' => 'ticket4',
                        'name' => 'VIP座',
                        'description' => '前排最佳观看位置，含签名海报',
                        'price' => 360,
                        'quantity_available' => 50
                    ],
                    [
                        'id' => 'ticket5',
                        'name' => '普通座',
                        'description' => '中排标准座位',
                        'price' => 180,
                        'quantity_available' => 100
                    ],
                    [
                        'id' => 'ticket6',
                        'name' => '学生票',
                        'description' => '需持有效学生证',
                        'price' => 60,
                        'quantity_available' => 50
                    ]
                ]
            ],
            [
                'id' => 'occ3',
                'name' => '北京站',
                'date_short' => '06.28',
                'full_date_time' => '2025.06.28 周五 20:00',
                'status_tag' => '预约',
                'venue_name' => '北京·大笑脱口秀俱乐部-三里屯店',
                'venue_address' => '北京市朝阳区工人体育场北路凯富大厦三层',
                'tickets' => [
                    [
                        'id' => 'ticket7',
                        'name' => 'VIP座',
                        'description' => '前排最佳观看位置，含签名海报',
                        'price' => 360,
                        'quantity_available' => 50
                    ],
                    [
                        'id' => 'ticket8',
                        'name' => '普通座',
                        'description' => '中排标准座位',
                        'price' => 180,
                        'quantity_available' => 100
                    ],
                    [
                        'id' => 'ticket9',
                        'name' => '学生票',
                        'description' => '需持有效学生证',
                        'price' => 60,
                        'quantity_available' => 50
                    ]
                ]
            ],
            [
                'id' => 'occ4',
                'name' => '广州站',
                'date_short' => '07.05',
                'full_date_time' => '2025.07.05 周五 19:30',
                'status_tag' => '预约',
                'venue_name' => '广州·大笑脱口秀俱乐部-天河店',
                'venue_address' => '广州市天河区天河路228号',
                'tickets' => [
                    [
                        'id' => 'ticket10',
                        'name' => 'VIP座',
                        'description' => '前排最佳观看位置，含签名海报',
                        'price' => 360,
                        'quantity_available' => 50
                    ],
                    [
                        'id' => 'ticket11',
                        'name' => '普通座',
                        'description' => '中排标准座位',
                        'price' => 180,
                        'quantity_available' => 100
                    ],
                    [
                        'id' => 'ticket12',
                        'name' => '学生票',
                        'description' => '需持有效学生证',
                        'price' => 60,
                        'quantity_available' => 50
                    ]
                ]
            ],
            [
                'id' => 'occ5',
                'name' => '成都站',
                'date_short' => '07.12',
                'full_date_time' => '2025.07.12 周五 19:00',
                'status_tag' => '预约',
                'venue_name' => '成都·大笑脱口秀俱乐部-春熙路店',
                'venue_address' => '成都市锦江区春熙路299号',
                'tickets' => [
                    [
                        'id' => 'ticket13',
                        'name' => 'VIP座',
                        'description' => '前排最佳观看位置，含签名海报',
                        'price' => 360,
                        'quantity_available' => 50
                    ],
                    [
                        'id' => 'ticket14',
                        'name' => '普通座',
                        'description' => '中排标准座位',
                        'price' => 180,
                        'quantity_available' => 100
                    ],
                    [
                        'id' => 'ticket15',
                        'name' => '学生票',
                        'description' => '需持有效学生证',
                        'price' => 60,
                        'quantity_available' => 50
                    ]
                ]
            ],
            [
                'id' => 'occ6',
                'name' => '杭州站',
                'date_short' => '07.19',
                'full_date_time' => '2025.07.19 周五 19:30',
                'status_tag' => '预约',
                'venue_name' => '杭州·大笑脱口秀俱乐部-西湖店',
                'venue_address' => '杭州市西湖区延安路258号',
                'tickets' => [
                    [
                        'id' => 'ticket16',
                        'name' => 'VIP座',
                        'description' => '前排最佳观看位置，含签名海报',
                        'price' => 360,
                        'quantity_available' => 50
                    ],
                    [
                        'id' => 'ticket17',
                        'name' => '普通座',
                        'description' => '中排标准座位',
                        'price' => 180,
                        'quantity_available' => 100
                    ],
                    [
                        'id' => 'ticket18',
                        'name' => '学生票',
                        'description' => '需持有效学生证',
                        'price' => 60,
                        'quantity_available' => 50
                    ]
                ]
            ],
            [
                'id' => 'occ7',
                'name' => '武汉站',
                'date_short' => '07.26',
                'full_date_time' => '2025.07.26 周五 19:00',
                'status_tag' => '预约',
                'venue_name' => '武汉·大笑脱口秀俱乐部-江汉路店',
                'venue_address' => '武汉市江汉区江汉路188号',
                'tickets' => [
                    [
                        'id' => 'ticket19',
                        'name' => 'VIP座',
                        'description' => '前排最佳观看位置，含签名海报',
                        'price' => 360,
                        'quantity_available' => 50
                    ],
                    [
                        'id' => 'ticket20',
                        'name' => '普通座',
                        'description' => '中排标准座位',
                        'price' => 180,
                        'quantity_available' => 100
                    ],
                    [
                        'id' => 'ticket21',
                        'name' => '学生票',
                        'description' => '需持有效学生证',
                        'price' => 60,
                        'quantity_available' => 50
                    ]
                ]
            ],
            [
                'id' => 'occ8',
                'name' => '重庆站',
                'date_short' => '08.02',
                'full_date_time' => '2025.08.02 周五 19:30',
                'status_tag' => '预约',
                'venue_name' => '重庆·大笑脱口秀俱乐部-解放碑店',
                'venue_address' => '重庆市渝中区民族路168号',
                'tickets' => [
                    [
                        'id' => 'ticket22',
                        'name' => 'VIP座',
                        'description' => '前排最佳观看位置，含签名海报',
                        'price' => 360,
                        'quantity_available' => 50
                    ],
                    [
                        'id' => 'ticket23',
                        'name' => '普通座',
                        'description' => '中排标准座位',
                        'price' => 180,
                        'quantity_available' => 100
                    ],
                    [
                        'id' => 'ticket24',
                        'name' => '学生票',
                        'description' => '需持有效学生证',
                        'price' => 60,
                        'quantity_available' => 50
                    ]
                ]
            ],
            [
                'id' => 'occ9',
                'name' => '南京站',
                'date_short' => '08.09',
                'full_date_time' => '2025.08.09 周五 19:00',
                'status_tag' => '预约',
                'venue_name' => '南京·大笑脱口秀俱乐部-新街口店',
                'venue_address' => '南京市玄武区中山路128号',
                'tickets' => [
                    [
                        'id' => 'ticket25',
                        'name' => 'VIP座',
                        'description' => '前排最佳观看位置，含签名海报',
                        'price' => 360,
                        'quantity_available' => 50
                    ],
                    [
                        'id' => 'ticket26',
                        'name' => '普通座',
                        'description' => '中排标准座位',
                        'price' => 180,
                        'quantity_available' => 100
                    ],
                    [
                        'id' => 'ticket27',
                        'name' => '学生票',
                        'description' => '需持有效学生证',
                        'price' => 60,
                        'quantity_available' => 50
                    ]
                ]
            ],
            [
                'id' => 'occ10',
                'name' => '西安站',
                'date_short' => '08.16',
                'full_date_time' => '2025.08.16 周五 19:30',
                'status_tag' => '预约',
                'venue_name' => '西安·大笑脱口秀俱乐部-钟楼店',
                'venue_address' => '西安市碑林区东大街118号',
                'tickets' => [
                    [
                        'id' => 'ticket28',
                        'name' => 'VIP座',
                        'description' => '前排最佳观看位置，含签名海报',
                        'price' => 360,
                        'quantity_available' => 50
                    ],
                    [
                        'id' => 'ticket29',
                        'name' => '普通座',
                        'description' => '中排标准座位',
                        'price' => 180,
                        'quantity_available' => 100
                    ],
                    [
                        'id' => 'ticket30',
                        'name' => '学生票',
                        'description' => '需持有效学生证',
                        'price' => 60,
                        'quantity_available' => 50
                    ]
                ]
            ]
        ]
    ];

    return Inertia::render('Public/EventDetail', [
        'event' => $placeholderEvent
    ]);
})->name('events.show');

Route::get('/events', [PublicEventController::class, 'index'])->name('events.index');

// Admin Routes for Site Settings
Route::prefix('admin')->name('admin.')->middleware(['auth'])->group(function () {
    // Example: Route::middleware(['auth', 'role:platform-admin'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('settings', [SiteSettingController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [SiteSettingController::class, 'update'])->name('settings.update');

    // Venues
    // Explicitly define POST route for update to handle multipart/form-data with _method spoofing
    // This MUST come BEFORE the Route::resource for venues to take precedence for POST requests to this URI pattern.
    Route::post('venues/{venue}', [VenueController::class, 'update'])->name('admin.venues.update.post'); // Ensure it uses the admin. prefix from the group if that's how routes are named
    Route::resource('venues', VenueController::class);

    // Categories
    Route::resource('categories', CategoryController::class);

    // Tags
    Route::resource('tags', TagController::class);

    // Editor Image Upload
    Route::post('editor/image-upload', [EditorUploadController::class, 'uploadImage'])->name('editor.image.upload');

    // Event CRUD
    Route::resource('events', EventController::class)->except(['show']);
    Route::resource('events.occurrences', EventOccurrenceController::class)->shallow();

    // Ticket Definitions CRUD
    Route::resource('ticket-definitions', TicketDefinitionController::class);

    // });
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');
    Route::get('/admin/dev/media-upload-test', [DevController::class, 'mediaUploadTest'])->name('admin.dev.media-upload-test');
    Route::post('/admin/dev/media-upload-test/post', [DevController::class, 'handleMediaPost'])->name('admin.dev.media-upload-test.post');
    Route::put('/admin/dev/media-upload-test/put', [DevController::class, 'handleMediaPut'])->name('admin.dev.media-upload-test.put');
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';

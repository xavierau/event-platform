<?php

namespace App\Http\Middleware;

use App\Enums\RoleNameEnum;
use App\Helpers\CurrencyHelper;
use App\Models\SiteSetting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'chatbot_enabled' => SiteSetting::get('enable_chatbot', true),
            'auth' => function () use ($request) {
                $user = $request->user();
                if ($user) {
                    $user->loadMissing('roles', 'activeOrganizers');

                    return [
                        'user' => array_merge($user->toArray(), [
                            'permissions' => $user->getAllPermissions()->pluck('name'),
                            'is_admin' => $user->hasRole(RoleNameEnum::ADMIN),
                            'is_organizer_member' => $user->hasOrganizerMembership(),
                            'organizer_ids' => $user->getOrganizerIds(),
                        ]),
                    ];
                }

                return ['user' => null];
            },
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'app_enums' => [
                'roles' => collect(RoleNameEnum::cases())->mapWithKeys(fn ($case) => [$case->name => $case->value])->all(),
            ],
            'currencySymbols' => CurrencyHelper::getAllSymbols(),
            'available_locales' => config('app.available_locales'),
            'locale' => app()->getLocale(),
            'translations' => function () {
                $locales = config('app.available_locales', ['en' => 'Eng']);
                $translations = [];
                foreach (array_keys($locales) as $locale) {
                    $jsonTranslations = file_exists(lang_path("{$locale}.json"))
                        ? json_decode(file_get_contents(lang_path("{$locale}.json")), true)
                        : [];
                    $phpTranslations = file_exists(lang_path("{$locale}/messages.php"))
                        ? require lang_path("{$locale}/messages.php")
                        : [];
                    $translations[$locale] = array_merge($phpTranslations, $jsonTranslations);
                }

                return $translations;
            },
        ];
    }
}

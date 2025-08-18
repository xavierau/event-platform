# Bug Report: QR Scanner Admin Access Issue

## Issue Description
**URL:** `/admin/qr-scanner`  
**User Role:** Organizer Admin  
**Status:** Critical - SQL Error  
**Date:** 2025-08-18  
**Branch:** hotfix/booking_scanner_issue  

### Problem
When an organizer admin visits the QR scanner page at `/admin/qr-scanner`, the application throws a database integrity constraint violation error.

### Error Details
**SQL Error:** `SQLSTATE[23000]: Integrity constraint violation: 1052 Column 'id' in field list is ambiguous`  
**Location:** `QrScannerController.php:193` → `getAccessibleEvents()` method  
**Root Cause:** Ambiguous column reference in database query when joining tables

### Impact
- Organizer admins cannot access the QR scanner functionality
- Prevents check-in operations for events
- Affects event management workflow

### Resolution
**Fixed:** 2025-08-18  
**Solution:** Updated QrScannerController to use qualified column names when plucking IDs from relationship queries.

**Changes Made:**
- Line 193: Changed `$user->activeOrganizers()->pluck('id')` to `$user->activeOrganizers()->pluck('organizers.id')`
- Line 213: Changed `$user->activeOrganizers()->pluck('id')` to `$user->activeOrganizers()->pluck('organizers.id')`

**Root Cause:** The `activeOrganizers()` relationship creates a join between `users` and `organizers` tables through a pivot table. Both tables have an `id` column, causing SQL to throw an ambiguous column error when `pluck('id')` is called without specifying the table name.

### Stack Trace
produciton log

[previous exception] [object] (PDOException(code: 23000): SQLSTATE[23000]: Integrity constraint violation: 1052 Column 'id' in field list is ambiguous at /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Database/Connection.php:404)
[stacktrace]
#0 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Database/Connection.php(404): PDO->prepare()
#1 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Database/Connection.php(809): Illuminate\\Database\\Connection->Illuminate\\Database\\{closure}()
#2 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Database/Connection.php(776): Illuminate\\Database\\Connection->runQueryCallback()
#3 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Database/Connection.php(395): Illuminate\\Database\\Connection->run()
#4 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Database/Query/Builder.php(3120): Illuminate\\Database\\Connection->select()
#5 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Database/Query/Builder.php(3378): Illuminate\\Database\\Query\\Builder->runSelect()
#6 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Database/Query/Builder.php(3695): Illuminate\\Database\\Query\\Builder->Illuminate\\Database\\Query\\{closure}()
#7 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Database/Query/Builder.php(3374): Illuminate\\Database\\Query\\Builder->onceWithColumns()
#8 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Database/Eloquent/Builder.php(1051): Illuminate\\Database\\Query\\Builder->pluck()
#9 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Support/Traits/ForwardsCalls.php(23): Illuminate\\Database\\Eloquent\\Builder->pluck()
#10 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Support/Traits/ForwardsCalls.php(52): Illuminate\\Database\\Eloquent\\Relations\\Relation->forwardCallTo()
#11 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Database/Eloquent/Relations/Relation.php(538): Illuminate\\Database\\Eloquent\\Relations\\Relation->forwardDecoratedCallTo()
#12 /home/forge/showeasy.ai/app/Http/Controllers/Admin/QrScannerController.php(193): Illuminate\\Database\\Eloquent\\Relations\\Relation->__call()
#13 /home/forge/showeasy.ai/app/Http/Controllers/Admin/QrScannerController.php(41): App\\Http\\Controllers\\Admin\\QrScannerController->getAccessibleEvents()
#14 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Routing/Controller.php(54): App\\Http\\Controllers\\Admin\\QrScannerController->index()
#15 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Routing/ControllerDispatcher.php(43): Illuminate\\Routing\\Controller->callAction()
#16 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Routing/Route.php(265): Illuminate\\Routing\\ControllerDispatcher->dispatch()
#17 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Routing/Route.php(211): Illuminate\\Routing\\Route->runController()
#18 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Routing/Router.php(808): Illuminate\\Routing\\Route->run()
#19 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(169): Illuminate\\Routing\\Router->Illuminate\\Routing\\{closure}()
#20 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Http/Middleware/AddLinkHeadersForPreloadedAssets.php(32): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}()
#21 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(208): Illuminate\\Http\\Middleware\\AddLinkHeadersForPreloadedAssets->handle()
#22 /home/forge/showeasy.ai/vendor/inertiajs/inertia-laravel/src/Middleware.php(86): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}()
#23 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(208): Inertia\\Middleware->handle()
#24 /home/forge/showeasy.ai/app/Http/Middleware/HandleAppearance.php(21): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}()
#25 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(208): App\\Http\\Middleware\\HandleAppearance->handle()
#26 /home/forge/showeasy.ai/app/Http/Middleware/SetLocale.php(29): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}()
#27 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(208): App\\Http\\Middleware\\SetLocale->handle()
#28 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Routing/Middleware/SubstituteBindings.php(50): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}()
#29 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(208): Illuminate\\Routing\\Middleware\\SubstituteBindings->handle()
#30 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Auth/Middleware/Authenticate.php(63): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}()
#31 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(208): Illuminate\\Auth\\Middleware\\Authenticate->handle()
#32 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/VerifyCsrfToken.php(87): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}()
#33 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(208): Illuminate\\Foundation\\Http\\Middleware\\VerifyCsrfToken->handle()
#34 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/View/Middleware/ShareErrorsFromSession.php(48): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}()
#35 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(208): Illuminate\\View\\Middleware\\ShareErrorsFromSession->handle()
#36 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Session/Middleware/StartSession.php(120): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}()
#37 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Session/Middleware/StartSession.php(63): Illuminate\\Session\\Middleware\\StartSession->handleStatefulRequest()
#38 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(208): Illuminate\\Session\\Middleware\\StartSession->handle()
#39 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Cookie/Middleware/AddQueuedCookiesToResponse.php(36): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}()
#40 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(208): Illuminate\\Cookie\\Middleware\\AddQueuedCookiesToResponse->handle()
#41 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Cookie/Middleware/EncryptCookies.php(74): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}()
#42 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(208): Illuminate\\Cookie\\Middleware\\EncryptCookies->handle()
#43 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(126): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}()
#44 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Routing/Router.php(807): Illuminate\\Pipeline\\Pipeline->then()
#45 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Routing/Router.php(786): Illuminate\\Routing\\Router->runRouteWithinStack()
#46 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Routing/Router.php(750): Illuminate\\Routing\\Router->runRoute()
#47 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Routing/Router.php(739): Illuminate\\Routing\\Router->dispatchToRoute()
#48 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php(200): Illuminate\\Routing\\Router->dispatch()
#49 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(169): Illuminate\\Foundation\\Http\\Kernel->Illuminate\\Foundation\\Http\\{closure}()
#50 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/TransformsRequest.php(21): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}()
#51 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/ConvertEmptyStringsToNull.php(31): Illuminate\\Foundation\\Http\\Middleware\\TransformsRequest->handle()
#52 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(208): Illuminate\\Foundation\\Http\\Middleware\\ConvertEmptyStringsToNull->handle()
#53 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/TransformsRequest.php(21): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}()
#54 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/TrimStrings.php(51): Illuminate\\Foundation\\Http\\Middleware\\TransformsRequest->handle()
#55 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(208): Illuminate\\Foundation\\Http\\Middleware\\TrimStrings->handle()
#56 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Http/Middleware/ValidatePostSize.php(27): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}()
#57 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(208): Illuminate\\Http\\Middleware\\ValidatePostSize->handle()
#58 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/PreventRequestsDuringMaintenance.php(109): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}()
#59 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(208): Illuminate\\Foundation\\Http\\Middleware\\PreventRequestsDuringMaintenance->handle()
#60 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Http/Middleware/HandleCors.php(48): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}()
#61 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(208): Illuminate\\Http\\Middleware\\HandleCors->handle()
#62 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Http/Middleware/TrustProxies.php(58): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}()
#63 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(208): Illuminate\\Http\\Middleware\\TrustProxies->handle()
#64 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/InvokeDeferredCallbacks.php(22): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}()
#65 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(208): Illuminate\\Foundation\\Http\\Middleware\\InvokeDeferredCallbacks->handle()
#66 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Http/Middleware/ValidatePathEncoding.php(26): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}()
#67 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(208): Illuminate\\Http\\Middleware\\ValidatePathEncoding->handle()
#68 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(126): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}()
#69 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php(175): Illuminate\\Pipeline\\Pipeline->then()
#70 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php(144): Illuminate\\Foundation\\Http\\Kernel->sendRequestThroughRouter()
#71 /home/forge/showeasy.ai/vendor/laravel/framework/src/Illuminate/Foundation/Application.php(1219): Illuminate\\Foundation\\Http\\Kernel->handle()
#72 /home/forge/showeasy.ai/public/index.php(20): Illuminate\\Foundation\\Application->handleRequest()
#73 {main}
"} 

<?php

/*
|--------------------------------------------------------------------------
| One-file updates
|--------------------------------------------------------------------------
|
| A site installs an update package only if it is signed with the private key
| whose public half is below. The private key lives on the developer's machine
| (see `php artisan update:keygen`) and never ships. This means a stolen
| super-admin login can reach the update page but cannot install code that was
| not built and signed by the developer.
|
| This value is deliberately not read from .env: whoever could edit .env could
| then also choose which packages the site trusts.
|
*/

return [

    'public_key' => 'lcQXHkZdFSoFb5io6hdHB3PC2xLGuFELDcFWr+PxI+U=',

    // Staging, snapshots and state for in-progress updates.
    'work_dir' => storage_path('app/updates'),

    // Largest package accepted by the upload form, in kilobytes. App-only
    // packages are ~2–3 MB; this leaves room without inviting abuse.
    'max_upload_kb' => 20 * 1024,

];

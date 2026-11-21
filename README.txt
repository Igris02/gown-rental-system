EXTRA GOWN PHOTOS UPDATE
========================
Adds up to 2 extra photos per gown. Admin uploads them on the gown form. On the gown page they show as
small 120 x 160 thumbnails under the main photo. Clicking one opens the same zoom view (1200 x 1600).

1. Commit or back up your project first.
2. Unzip this folder's contents into your Laravel project root (it adds 4 new files and
   apply_extra_photos.php next to artisan).
3. Run:   php apply_extra_photos.php
   It checks every edit before writing. If any file does not match, it stops and changes nothing.
4. Run:   php artisan migrate
5. Delete apply_extra_photos.php (optional).

New files:
  database/migrations/2026_11_21_000010_create_gown_photos_table.php
  app/Models/GownPhoto.php
  resources/views/partials/extra-photos.blade.php
  resources/views/admin/gowns/_extra-photos.blade.php

Files the script edits:
  app/Models/Gown.php, app/Http/Requests/GownRequest.php
  app/Http/Controllers/Admin/GownController.php, app/Http/Controllers/CatalogController.php
  resources/views/catalog/show.blade.php, resources/views/admin/gowns/_form.blade.php
  public/js/catalog.js, public/js/admin.js, public/css/catalog.css, public/css/admin.css

Notes:
  - Extra photos need a main photo first (they are shown under it).
  - Replacing or removing an extra photo also deletes the old file from Cloudinary.
  - The script also repairs a missing <div class="field"> around Silhouette in _form.blade.php,
    if your copy has that problem.

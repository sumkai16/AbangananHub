# Image cleanup and a smaller demo dataset

**Status: in progress (Nov 6 2026).** Steps 1, 2, 3 and 5 are built and tested (suite 1743 passed). Step 4 is done: Axcee approved the list and the dev database now holds 22 properties (backup in `storage/app/backups/`, see CHANGELOG). Delete this plan after Axcee's manual pass.

**Measured (Chrome, dev data, same photos old size against now):** admin properties list 871 KB to 202 KB (-77%), admin property page 515 KB to 142 KB (-72%), admin units list 335 KB to 157 KB (-53%). One 1200px photo: 166 KB JPEG, 38 KB AVIF at card, 8 KB at thumb.

## Why

Pages are slow because of image weight, not queries (see `plans/performance-optimization.md`: 4.4 MB of eager images on the home page). That plan fixed the browse cards. Three gaps are left:

1. Uploads are stored as the original (up to 5 MB each, any dimension). No upload call limits size or format.
2. 58 view lines print `media_url` without going through `Images::resize`, so those pages can serve an original.
3. The dev data is large and repetitive: 88 properties, 723 photo rows that point at only 35 distinct Unsplash photos.

## Standard format

**WebP (AVIF where the browser supports it), JPEG fallback**, resized to the screen it is shown on, quality about 75 to 80. Cloudinary does the conversion on delivery with `f_auto,q_auto`; `Images::resize()` already adds that.

| Name | Width | Used for | Target size |
|---|---|---|---|
| `thumb` | 160 | list rows, avatars of a unit | under 15 KB |
| `card` | 640 | cards and grids | under 80 KB |
| `gallery` | 1200 | property and unit page galleries | under 250 KB |
| `full` | 1600 | full-screen lightbox | under 400 KB |

Nothing wider than 2000px is stored.

## Decisions

- **Shrink at upload, keep the 5 MB per-file limit.** Cloudinary shrinks the file on arrival (longest side 2000px, `q_auto:good`), so a large phone photo is not refused and tenants never receive the big file. Format stays as uploaded; delivery converts it.
- **New uploads capped at 6 photos** per property and per unit (a unit still needs at least 3). Applies to the web forms and the mobile API. Existing records above the cap are not touched (the demo data has exactly 3 each).
- **No change to videos** (up to 100 MB, one per unit) or to profile photos, logos and ID images: those have their own rules.
- **Demo data to about 30 properties.** The 10 `[escrow-fixture]` rows are removed with their own command (`php artisan escrow:scenarios --clean`). The 14 real properties that carry reservations or chats stay. About 6 more stay for a spread of landlords, cities and types. The rest (about 48) go, after Axcee signs off the list. `PropertySeeder` produces the smaller set so a reseed does not bring back 88.

## Changes

| # | Change | Files |
|---|---|---|
| 1 | `ImageUploader::listingPhoto($folder)` returns the Cloudinary options with the incoming transformation. The 9 property and unit photo uploads use it (video and profile uploads do not). | `app/Services/ImageUploader.php`, `Landlord/PropertyWizardController`, `PropertyController`, `Landlord/PropertyUnitController`, `Api/Landlord/PropertyWriteController`, `Api/Landlord/UnitWriteController` |
| 2 | New `App\Support\ListingPhotos` (`MAX_PROPERTY = 6`, `MAX_UNIT = 6`, `MIN_UNIT = 3`, `MAX_KB = 5120`, `tooMany()`). Used by every photo rule: property create, update and wizard, unit create and update (the unit update now also counts photos it already has), web and API. Form hints and the photo-capture script's limit follow it. | `StorePropertyRequest`, `UpdatePropertyRequest`, `PropertyWizardController`, `PropertyUnitController`, `Api/Landlord/UnitWriteController`, `landlord/properties/wizard/location`, `landlord/units/partials/_photo-capture` |
| 3 | `Images` gets `thumb()`, `card()`, `gallery()`, `full()` and `Images::src($url, 'card')`. The raw `media_url` prints use the right size; srcset on card and gallery images. | `app/Support/Images.php` and the views listed by `grep -rn media_url resources/views` |
| 4 | Trim the demo data (needs sign-off, see below). | dev database, `database/seeders/PropertySeeder.php` |
| 5 | Measure bytes and request counts before and after on `/`, `/properties`, `/properties/{id}`, a landlord list and an admin list. | none |

## Step 4: delete list (waiting for Axcee)

Approved and executed Nov 6 2026. A `mysqldump` of `abanganan_hub` goes to `storage/app/backups/` first (`/usr/bin/mysqldump` is installed). Deleting a property cascades its units, photos, reviews, reservations, conversations and activity.

| Group | Count | Ids |
|---|---:|---|
| `[escrow-fixture]` (removed with `php artisan escrow:scenarios --clean`) | 10 | 168 to 177 |
| Keep: reservations or conversations | 14 | 1, 2, 3, 4, 7, 8, 10, 13, 16, 19, 22, 25, 28, 64 |
| Keep: spread of landlord, city and type | 8 | 15, 27, 31, 35, 39, 43, 47, 51 |
| **Delete** | **56** | 5, 6, 9, 11, 12, 14, 17, 18, 20, 21, 23, 24, 26, 29, 30, 32, 33, 34, 36, 37, 38, 40, 41, 42, 44, 45, 46, 48, 49, 50, 52, 53, 54, 55, 56, 57, 58, 59, 60, 61, 62, 63, 65, 66, 67, 68, 69, 70, 71, 72, 73, 74, 75, 76, 77, 78 |

Result: 88 properties become 22. Cost: 116 units and 238 reviews go with the 56 (77 of the 88 properties have reviews, so browse will look quieter). Every landlord that has properties today keeps at least one.

## Risks

- **Step 4 deletes dev rows,** including reviews and reservations. The dump and the listed delete set are the safety net.
- **Step 3 touches about 30 views.** A wrong size shows a blurry or oversized image. Check each page type once (list below).
- **Old uploads stay big** until re-uploaded. This fixes new uploads only; the dev database holds only Unsplash images, so nothing there needs migrating.
- **A transformation error would block a listing upload.** The option set is standard Cloudinary (`width`, `height`, `crop: limit`, `quality`); the upload test with a fake uploader cannot prove Cloudinary accepts it, so step 3 of the guide uploads a real photo.

## Tests

- `ListingPhotosTest`: property and unit photo caps (create and update, web and API), the unit update counts existing photos, the message names the limit, a unit still needs 3.
- `ImagesTest`: each named size produces the right Cloudinary and Unsplash URL; a host we do not control comes back untouched.
- `ImageUploaderTest`: `listingPhoto()` carries the 2000px limit and `q_auto`, and the folder.
- View sweep: a test that fails if any listed view prints `media_url` outside `Images::` (a grep over `resources/views`), with a short allow-list for the places that must use the original (the lightbox `full` link is `Images::full`, so none expected).

## Manual test guide

Run `npm run build` first (new classes). Sign in as the seeded landlord, then a tenant, then the admin.

1. **Upload shrinks:** add a property photo of 4 to 5 MB (a phone photo). In Cloudinary, the stored image is 2000px or less on its longest side and well under 1 MB.
2. **Cap:** adding a 7th photo to a property, or to a unit, is refused with "at most 6 photos total". A unit with 4 photos plus 3 new ones is refused on edit. A unit with 2 photos is still refused (needs 3).
3. **Real upload works:** create a unit with 3 real photos; the save succeeds and the photos show.
4. **Tenant pages:** `/properties`, a property page and its gallery, favorites, My Reservations, My Rentals: images sharp, none blurry, none oversized. In DevTools Network, no single image is over about 250 KB except the lightbox.
5. **Landlord pages:** properties list, units list, reservations list, a property page: thumbnails sharp.
6. **Admin pages:** properties and units catalogues, approval queues, property page photo strip and unit page: thumbnails sharp, the strip opens the full image.
7. **Before and after:** DevTools Network, hard reload `/` and `/properties`; compare "transferred" with the numbers in the CHANGELOG entry.
8. **Smaller dataset (after step 4):** about 30 properties on browse; every landlord and tenant seeded account still has something to show; escrow fixtures are gone and `php artisan escrow:scenarios` recreates them.

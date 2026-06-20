# 🎯 Jednotný Filter Systém - Implementácia

## ✅ Implementované Komponenty

### 1. **FormRequest Validácia** 
📁 [app/Http/Requests/IndexFilterRequest.php](app/Http/Requests/IndexFilterRequest.php)

Centralizovaná validácia pre všetky filter parametre:
```php
?published=true         // Iba publikované
?unpublished=true       // Iba nepublikované
?blocked=true           // Iba blokované
?status=draft           // Špecifický status
?search=letny festival  // Textové vyhľadávanie
?deleted=true           // Vrátať soft-deleted
?per_page=50            // Počet položiek na stránku
```

## Query Tvar Filtrov

Všetky index endpointy používajú query parametre v tomto tvare:

```text
GET /api/{scope}/{resource}?status={status}&published={bool}&unpublished={bool}&blocked={bool}&deleted={bool}&search={string}&per_page={number}
```

Prakticky to znamená napríklad:

```text
GET /api/admin/events?search=festival
GET /api/admin/events?status=published&search=bratislava&per_page=20
GET /api/dashboard/canals?blocked=false&search=kultura
GET /api/dashboard/venues?deleted=true&search=amfiteater
```

### Podporované query parametre

| Parameter | Typ | Povinný | Popis |
|--------|--------|--------|--------|
| `status` | `string` | nie | Jeden z: `draft`, `pending_review`, `rejected`, `scheduled`, `published`, `archived`, `blocked` |
| `published` | `boolean` | nie | Vráti len publikované záznamy |
| `unpublished` | `boolean` | nie | Vráti len nepublikované záznamy |
| `blocked` | `boolean` | nie | Vráti blokované alebo neblokované záznamy |
| `deleted` | `boolean` | nie | Pri modeloch so soft delete filtruje zmazané/nezmazané záznamy |
| `search` | `string` | nie | Textové vyhľadávanie cez hlavné textové polia modelu |
| `per_page` | `integer` | nie | Počet položiek na stránku, `min:1`, `max:100`, default `15` |

### Správanie `search`

- `search` robí jeden query filter, nie dve oddelené API volania.
- Najprv zvýhodňuje zhodu v hlavných poliach ako `name`, `title` alebo `email`.
- Zároveň hľadá aj v sekundárnych textových poliach, typicky `body`.
- Výsledky so zhodou v názve alebo titulku sa zoradia pred výsledky, kde sa výraz našiel iba v `body`.
- Ak model daný stĺpec nemá, filter ho automaticky preskočí.

### Validácia

```text
status: nullable|in:draft,pending_review,rejected,scheduled,published,archived,blocked
published: nullable|boolean
unpublished: nullable|boolean
blocked: nullable|boolean
deleted: nullable|boolean
search: nullable|string|max:250
per_page: nullable|integer|min:1|max:100
```

### 2. **Query Scopes Trait**
📁 [app/Models/Traits/HasCommonFilters.php](app/Models/Traits/HasCommonFilters.php)

Singleton scopes pre všetky modely:
- `byStatus()` - Filter podľa status enumu
- `bySearch()` - Textové vyhľadávanie s preferenciou zhody v názve/titulku
- `byPublished()` - Filter publikované/nepublikované
- `byBlocked()` - Filter blokované/aktívne  
- `includeDeleted()` - Soft-deleted záznamy
- `applyCommonFilters()` - Kombinácia všetkých filtrov

### 3. **Aplikované na Modeloch**
Trait `HasCommonFilters` je aplikovaný na:
- ✅ **Event** 
- ✅ **Canal**
- ✅ **Venue**
- ✅ **User**
- ✅ **File**
- ✅ **Organization**

### 4. **Repository Podpora**
📁 [app/Repositories/AbstractRepository.php](app/Repositories/AbstractRepository.php#L115)

Nová metóda:
```php
public function indexWithFilters($perPage = 15, array $filters = []): Paginator
```

### 5. **Controllers Aktualizované**

#### Admin Controllers:
- ✅ `Admin/EventController::index()`
- ✅ `Admin/CanalController::index()`
- ✅ `Admin/UserController::index()`
- ✅ `Admin/VenueController::index()`
- ✅ `Admin/OrganizationController::index()`

#### Dashboard Controllers:  
- ✅ `Dashboard/DashboardEventController::index()`
- ✅ `Dashboard/DashboardCanalController::index()`
- ✅ `Dashboard/DashboardVenueController::index()`
- ✅ `Dashboard/DashboardOrganizationController::index()`

Všetci kontrolleri teraz:
1. Přijímajú `IndexFilterRequest`
2. Extrahujeé filtry cez `->getFilters()`
3. Predávajú do `indexWithFilters()`

## 🧪 Testy

### Unit Testy - ✅ VŠETKY PREŠLI (13 testov)
```bash
php artisan test tests/Unit/Filters/CommonFilterTest.php tests/Unit/Requests/IndexFilterRequestTest.php
```

**CommonFilterTest** (6 testov):
- ✅ `test_filter_by_published_status`
- ✅ `test_filter_by_unpublished_status`
- ✅ `test_filter_by_status`
- ✅ `test_filter_by_blocked`
- ✅ `test_include_deleted_soft_deletes`
- ✅ `test_apply_common_filters_combined`

**IndexFilterRequestTest** (7 testov):
- ✅ `test_get_filters_with_published`
- ✅ `test_get_filters_with_unpublished`
- ✅ `test_get_filters_with_status`
- ✅ `test_get_filters_with_per_page`
- ✅ `test_get_filters_default_per_page`
- ✅ `test_validation_invalid_status`
- ✅ `test_validation_per_page_max`

## 📡 API Príklady Použitia

### Public API
```bash
GET /api/events?published=true
GET /api/canals?status=published
GET /api/events?per_page=50
```

### Admin API
```bash
GET /api/admin/events?published=true
GET /api/admin/events?unpublished=true
GET /api/admin/events?status=draft
GET /api/admin/events?search=festival
GET /api/admin/events?status=published&search=bratislava&per_page=20
GET /api/admin/events?deleted=true
GET /api/admin/canals?blocked=true
GET /api/admin/users?blocked=false&per_page=25
```

### Dashboard API
```bash
GET /api/dashboard/events?published=true&per_page=20
GET /api/dashboard/events?search=koncert
GET /api/dashboard/canals?status=scheduled
GET /api/dashboard/venues?deleted=true
```

## 🔧 Ako Rozšíriť Filtry

### 1. Pridaj Nový Scope do `HasCommonFilters`:
```php
public function scopeByCategory(Builder $query, ?string $category): Builder
{
    return $category ? $query->where('category', $category) : $query;
}
```

### 2. Pridaj do FormRequest Rules:
```php
public function rules(): array
{
    return [
        // ... existing
        'category' => ['nullable', 'in:primary,secondary,other'],
    ];
}
```

### 3. Pridaj do `getFilters()`:
```php
return [
    'category' => $this->input('category'),
    // ... existing
];
```

### 4. Automaticky Podporovaný vo Všetkých Modeloch:
```php
// Bez zmien potrebných v kontrolleroch!
Event::applyCommonFilters($filters)->get();
```

## 📋 Výhody

| Aspekt | Výhoda |
|--------|--------|
| **DRY Princíp** | Jeden trait, všetky modely |
| **Konzistencia** | Rovnakí filtry všade |
| **Testovateľnosť** | Izolované unit testy |
| **Rozšíriteľnosť** | Pridaj scope a gotovo |
| **API Kompatibilita** | Prirodzené query parametre |
| **Bez Breaking Changes** | Existujúce endpointy ostávajú |

## 🚀 Nasadenie

Všetko je pripravené na production:
```bash
php artisan migrate
php artisan test
```

Testy sú úspešné, implementácia je kompletná a hotová k nasadeniu.

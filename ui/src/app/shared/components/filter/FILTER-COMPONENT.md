# Filter Component

Shared filter komponenta s podporou URL query parametrov pre filtráciu, pagination a status manažment.

## Funkcie

- ✅ **Published** - filtrovanie publikovaných položiek
- ✅ **Unpublished** - filtrovanie nepublikovaných položiek  
- ✅ **Blocked** - filtrovanie blokovaných položiek
- ✅ **Status** - filtrovanie podľa špecifického statusu (draft, pending_review, etc.)
- ✅ **Deleted** - zobrazovanie soft-deleted položiek
- ✅ **Search** - textové vyhľadávanie cez `search` query parameter
- ✅ **Pagination** - počet položiek na stránku (10, 20, 50, 100)
- ✅ **URL Query Params** - automatická synchronizácia s URL

## Použitie v API Service

```typescript
import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { FilterQueryService } from '../../shared/services/filter-query.service';
import { filterStateToHttpParams } from '../../shared/utils/filter-http-params.utils';

@Injectable({ providedIn: 'root' })
export class ItemsApiService {
  private readonly http = inject(HttpClient);
  private readonly filterQuery = inject(FilterQueryService);

  listItems() {
    const filterState = this.filterQuery.getFilterState();
    const params = filterStateToHttpParams(filterState);

    return this.http.get('/api/items', { params });
  }
}
```

## URL Príklady

```
/items?published=true              // Iba publikované
/items?unpublished=true            // Iba nepublikované
/items?blocked=true                // Iba blokované
/items?status=draft                // Špecifický status
/items?deleted=true                // Vrátať soft-deleted
/items?search=letny festival       // Textové vyhľadávanie
/items?per_page=50                 // 50 položiek na stránku
/items?published=true&search=letny festival&per_page=100 // Kombinované filtre
```

## Automatická Integrácia

Filter se automaticky zobrazuje v `app-index-shell` componente, ktorá je základom pre všetky index/list stránky.

Komponenta automaticky:
1. Načítava filtre z URL query parametrov
2. Aktualizuje URL pri zmene filtrov
3. Synchronizuje state s router navigation

## FilterQueryService API

```typescript
// Získať aktuálny filter state
getFilterState(): FilterState

// Subscribovať na zmeny filter state
getFilterState$(): Observable<FilterState>

// Aktualizovať filter
updateFilter(partial: Partial<FilterState>): void

// Reset všetkých filtrov
resetFilter(): void

// Toggle konkrétnych filtrov
togglePublished(): void
toggleUnpublished(): void
toggleBlocked(): void
toggleDeleted(): void

// Nastaviť textové vyhľadávanie
setSearch(search: string): void

// Nastaviť status
setStatus(status: ModelStatus | null): void

// Nastaviť počet položiek na stránku
setPerPage(perPage: number): void

// Skontrolovať či sú filtre aktívne
isActive(): boolean
```

## FilterState Model

```typescript
interface FilterState {
  published: boolean;        // published=true
  unpublished: boolean;      // unpublished=true
  blocked: boolean;          // blocked=true
  status: string | null;     // status=draft, pending_review, etc.
  deleted: boolean;          // deleted=true
  search: string;            // search=letny festival
  per_page: number;          // per_page=20
}
```

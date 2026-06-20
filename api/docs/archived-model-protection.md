# Archived Model Protection

## Čo to robí

Modely s `status = archived` nie je možné upravovať ani mazať cez API. Pokus o update alebo delete vráti **HTTP 403 Forbidden**.

Platí pre: **Event, Venue, Canal, Organization, User**

---

## Implementácia

### `ModelStatus::isArchived()`
`app/Enums/ModelStatus.php`

```php
public function isArchived(): bool
{
    return $this === self::Archived;
}
```

### Trait `DeniesArchivedUpdate`
`app/Policies/Traits/DeniesArchivedUpdate.php`

Spoločný trait pre všetky policies. Obsahuje helper `isNotArchived(object $model): bool`.

### Policy `update()` a `delete()` check

Každá z 5 policies používa trait a v `update()` aj `delete()` kontroluje:

```php
use App\Policies\Traits\DeniesArchivedUpdate;

class EventPolicy
{
    use DeniesArchivedUpdate;

    public function update(User $user, Event $event): bool
    {
        return $this->isNotArchived($event)
            && $user->dashboardCanalIds()->contains((int) $event->canal_id);
    }

    public function delete(User $user, Event $event): bool
    {
        return $this->isNotArchived($event)
            && $user->ownedCanals()->where('canals.id', $event->canal_id)->exists();
    }
}
```

Rovnaký pattern: `VenuePolicy`, `CanalPolicy`, `OrganizationPolicy`, `UserPolicy`.

---

## Správanie

| Stav modelu | `update()` / `delete()` policy | HTTP |
|-------------|--------------------------------|------|
| draft / published / iný | prechádza na ownership check | 200 alebo 403 podľa vlastníctva |
| `archived` | vráti `false` ihneď | **403 Forbidden** |

---

## Pridanie nového modelu

1. Uisti sa, že model má `status` stĺpec s `ModelStatus` castom.
2. V príslušnej Policy pridaj `use DeniesArchivedUpdate;`.
3. V `update()` aj `delete()` prepend `$this->isNotArchived($model) &&`.

---
name: Service-AI-workflow
description: "Zmyslom služby z textu zozbierať informácie o evente aby príspevok mohol byť publikovaný"
---

# Laravel API Workflow

## Purpose
Upraviť text aby bol gramaticky, štylistický správny a získať z textu informácie aby mohol byť event publikovaný.

## Content
- Opraviť gramaticky a štylisticky
- Ak je text krátky, doplniť podľa pokynov
- Získať dátum a čas začiatku eventu a koniec.
- Ak koniec eventu nie je uvedený alebo je nelogický voči začiatku, nastaviť event ako celodenný (od 00:00:00 do 23:59:59 v deň začiatku).
- Zistiť kto organizuje event, nájsť v databaze alebo vytvoriť nový canál
- Zistiť kde bude event, nájsť v databaze príslušný venue, alebo ak nie vytvoriť nový venue
- Osoby ktoré sú v texte uvedené, prípadne kontakty, z nich vytvoriť nové canal ak neexistujú
- Režim bez `--save`: iba analyzovať a vypísať výsledok bez zápisu do DB.
- Režim s `--save`: uložiť výsledok do eventu, vrátane priradenia `canal_id`, `venue_id` a `user_id=1` (system owner).
- Pri `--save` vždy zabezpečiť pivot záznam v `canal_user` pre `user_id=1` a cieľový canal (`is_owner=1`, `is_active=1`).
- Pri `--save` ak nájdené venue patrí inému canalu, presunúť `venue.canal_id` na canal eventu.
- php artisan app:ai-detect-from-text 42


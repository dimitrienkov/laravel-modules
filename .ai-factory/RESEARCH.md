# Research

Updated: 2026-05-27 23:55
Status: active

## Active Summary (input for /aif-plan)
<!-- aif:active-summary:start -->
Topic: Добавление `meta.group` в manifest и `source` descriptor в state.json
Goal: Группировка модулей по бизнес-области в UI/CLI + хранение provenance для будущих обновлений
Constraints:
- `meta.group` — optional string в module.json, kebab-case (`/^[a-z][a-z0-9-]*$/`), чисто презентационное
- `schema_version` остаётся 1 — optional field
- Display labels для групп — в хост-конфиге `config/modules.php`, не в manifest
- `source` — новая секция в state.json (host-owned, mutable)
- Два отдельных enum: `ModuleSourceKind` (staging, Zip only) и `ModuleOriginKind` (provenance: Local | Zip)
- `source` в state.json, НЕ в отдельном файле — state.json уже host-owned lifecycle + runtime
- Registry cache не затрагивается — source живёт в state.json
- Directory install удалён — модуль создают scaffold'ом или ставят из zip
- locator удалён — provenance хранит kind + installed_version + ?checksum, без мёртвых ссылок на fs
Decisions:
- **Group**: строка-code в `meta.group` + хост-конфиг `modules.groups` для display labels
- **Provenance в state.json**: секция `source` внутри state.json, а не отдельный файл. Причины: state.json уже host-owned и mutable; `installed_at`/`updated_at` уже lifecycle; третий файл — лишняя сложность
- **Два enum**: `ModuleSourceKind` (Application/Enums, staging: `Zip`) отвечает «что дали на вход?», `ModuleOriginKind` (Manifest/Enums, provenance: `Local` | `Zip`) отвечает «откуда модуль?» — разные концепции, разные слои
- **Checksum**: sha256, считается при install из zip, до распаковки
- **ModuleOrigin VO**: kind, installedVersion, ?checksum + `withInstalledVersion()` для update
- **No locator**: путь к zip после установки мёртв — не хранить
- **No directory install**: staging pipeline только zip; scaffold — отдельный UseCase
- `ModuleStateDocument` расширяется: state + values + ?origin
Open questions: нет (все вопросы разрешены)
Success signals:
- module.json с `meta.group: "content"` проходит валидацию, без group — тоже
- `modules:list` показывает колонку Group
- Scaffold записывает `source.kind: "local"`, `source.installed_version` в state.json
- Install из zip записывает `source.kind: "zip"`, `installed_version`, `checksum`
- Update обновляет `source.installed_version`
- Directory install отсутствует, `ModuleSourceKind::Directory` удалён
Next step: `/aif-implement` для реализации плана
<!-- aif:active-summary:end -->

## Sessions
<!-- aif:sessions:start -->
### 2026-05-27 23:45 — meta.group + source descriptor исследование

What changed:
- Исследован вопрос добавления `meta.group` в manifest для группировки модулей в UI/CLI
- Сравнены три варианта: A) просто строка, B) объект {code, label}, C) строка-code + хост-конфиг
- Выбран вариант C: code в manifest, display в `config/modules.php`
- Исследован вопрос хранения provenance (откуда установлен модуль)
- Проанализирована рекомендация ChatGPT 5.5 о третьем файле source.json — отклонена
- Решение: секция `source` в state.json (host-owned, уже содержит lifecycle данные)
- Спроектирован `ModuleOrigin` VO и `ModuleOriginKind` enum
- Разделены концепции: `ModuleSourceKind` (staging: directory/zip) vs `ModuleOriginKind` (provenance: local/zip/registry/git)
- Спроектирован update discovery flow для будущих registry-модулей

Key notes:
- `FeatureDefinition` уже имеет `?group` — это для группировки settings внутри модуля, не путать с `meta.group` для группировки модулей
- `ModuleSourceKind` (Application/Enums) — staging concept, не трогать
- `ModuleOriginKind` (Manifest/Enums) — provenance concept, новый enum
- state.json совмещает lifecycle (installed_at, updated_at) и runtime (enabled, values) — source логично добавить к lifecycle
- Третий файл source.json отклонён: лишняя точка отказа, лишняя сложность, state.json уже mutable host-owned
- Для auto-update discovery нужен registry API + `source.registry_url` + `source.channel` — закладываем в VO, не реализуем

### 2026-05-27 23:55 — /aif-improve v2: удаление directory install, enum split, drop locator

What changed:
- **Directory install удалён**: `ModuleSourceKind::Directory`, `prepareFromDirectory()`, directory-тесты — всё убрать. Модуль создают scaffold'ом или ставят из zip, третьего не дано.
- **Два enum вместо одного**: staging (`ModuleSourceKind`: `Zip`) и provenance (`ModuleOriginKind`: `Local` | `Zip`). Staging = «что на входе pipeline?», provenance = «откуда модуль?».
- **locator убран**: путь к zip-файлу после установки — мёртвая ссылка. Provenance = kind + installed_version + ?checksum.
- **ModuleOrigin VO упрощён**: три поля вместо семи (убраны locator, registry_url, channel, checked_at — future scope без реализации).
- **План перестроен**: Phase 0 (staging cleanup) → Phase 1 (meta.group) → Phase 2 (origin VO + state) → Phase 3 (lifecycle) → Phase 4 (CLI)

Key notes:
- `ModuleSourceKind` после удаления `Directory` остаётся с единственным кейсом `Zip` — для типизации staging pipeline этого достаточно
- `ModuleOriginKind` живёт в `Manifest/Enums/` — это provenance-концепт, часть manifest layer
- `PreparedSource` получит `?string $checksum` для передачи sha256 из preparer в UseCase
- `ScaffoldModuleUseCase` → `ModuleOrigin::forLocal(version)`, `InstallModuleUseCase` → `ModuleOrigin::forZip(version, checksum)`
- `UpdateModuleUseCase` → читает origin из document, если есть → `withInstalledVersion(newVersion)`, если нет → null

Links (paths):
- План: `.ai-factory/plans/feature-meta-group-source-origin.md` (v2)
- `src/Application/Enums/ModuleSourceKind.php` — удалить `Directory`, оставить `Zip`
- `src/Application/Support/ModuleSourcePreparer.php` — удалить `prepareFromDirectory()`, упростить `prepare()`
- `src/Manifest/Enums/ModuleOriginKind.php` — новый enum (Local, Zip)
- `src/Manifest/VO/ModuleOrigin.php` — новый VO (kind, installedVersion, ?checksum)
- `src/Manifest/VO/ModuleStateDocument.php` — добавить ?origin
- `src/Manifest/ModuleStateRepository.php` — парсить/писать source секцию
<!-- aif:sessions:end -->

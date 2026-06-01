# Runtime-правила

> Правила для runtime, кеширования и Octane-совместимости. Загружаются после `rules/base.md`.

## Правила

- Singleton-сервисы должны быть immutable или stateless; любой per-request mutable cache должен жить в scoped binding.
- `FeatureRepositoryInterface` должен оставаться scoped и читать значения фичетоглов из актуального `state.json` через `ModuleStateRepositoryInterface::readValues()`, а не из optimized-кеша registry.
- Используй кеш `modules:optimize` только для обнаружения модулей и load order; никогда не используй его как runtime-источник значений фичетоглов.
- Не добавляй mutable static properties или глобальное состояние приложения в `src/`.
- Защищай optional integrations проверками классов/контейнера, чтобы пакет загружался без установленных MoonShine или Inertia.
- Не храни `Application`, `Request` или `Router` как mutable long-lived state в singleton-сервисах.

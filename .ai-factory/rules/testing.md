# Правила тестирования

> Правила для тестов и quality gates. Загружаются после `rules/base.md`.

## Правила

- Покрывай каждый лоадер unit-тестами на успешную загрузку, ранний return при отсутствующем пути и идемпотентный повторный запуск там, где это наблюдаемо.
- Покрывай запись манифеста тестами на schema validation, atomic write behavior и file locking.
- Покрывай сортировку зависимостей тестами на валидные графы, изолированные модули, отсутствующие зависимости и циклы.
- Покрывай команды жизненного цикла feature-тестами для install, update, remove, enable, disable, list, optimize и optimize-clear behavior.
- Покрывай `FeatureRepository` тестами, доказывающими per-request cache и отсутствие stale values между симулированными scoped requests.
- Держи архитектурные тесты на strict types, layer boundaries, отсутствие debug-функций, отсутствие фасадов и хелперов в `src/`, отсутствие mutable static state, readonly DTO/VO classes и соответствие loader contract.
- Предпочитай unit-тесты для выделенной доменной/Application-логики; feature-тесты оставляй для provider bindings, Artisan commands и интеграционных границ Laravel.
- Структурируй тесты как Arrange-Act-Assert: один основной action и одно наблюдаемое поведение на тест.
- Держи setup теста рядом со сценарием или в явно названном helper/factory; не прячь важные входные данные в глобальной подготовке.
- Тесты не должны зависеть от порядка запуска, времени ожидания или состояния других тестов; вместо `sleep()` используй fakes, mocks, синхронное выполнение или управляемое время.

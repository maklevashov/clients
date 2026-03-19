<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Клиенты</title>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="app-container">
    <!-- Заголовок с поиском -->
    <div class="clients-header">
        <h1>Клиенты</h1>
        <div class="search-box">
            <input type="text" id="clientSearch" placeholder="Поиск">
            <span class="search-icon">🔍</span>
        </div>
    </div>

    <!-- Список клиентов -->
    <div class="clients-list">
        {% for client in clients %}
        <div class="client-item" data-name="{{ client.name|lower }}" data-phone="{{ client.phone }}">
            <span class="client-name">{{ client.name }}</span>
            <span class="client-phone">{{ client.phone }}</span>
        </div>
        {% endfor %}
    </div>

    <!-- Кнопка добавления клиента -->
    <button class="add-client-btn">+</button>

    <!-- Нижняя навигация -->
    <div class="bottom-nav">
        <button class="nav-item" data-page="journal">
            <span class="icon">📋</span>
            <span>Журнал</span>
        </button>
        <button class="nav-item" data-page="schedule">
            <span class="icon">📅</span>
            <span>График</span>
        </button>
        <button class="nav-item active" data-page="clients">
            <span class="icon">👥</span>
            <span>Клиенты</span>
        </button>
        <button class="nav-item" data-page="notifications">
            <span class="icon">🔔</span>
            <span>Уведомление</span>
        </button>
        <button class="nav-item" data-page="more">
            <span class="icon">⋯</span>
            <span>Еще</span>
        </button>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(document).ready(function() {
        // Поиск клиентов
        $('#clientSearch').on('input', function() {
            const searchTerm = $(this).val().toLowerCase();

            $('.client-item').each(function() {
                const name = $(this).data('name');
                const phone = $(this).data('phone');

                if (name.includes(searchTerm) || phone.includes(searchTerm)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });

        // Навигация
        $('.nav-item').click(function() {
            const page = $(this).data('page');
            if (page === 'journal') {
                window.location.href = '/';
            }
        });
    });
</script>
</body>
</html>
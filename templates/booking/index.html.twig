<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Запись клиентов</title>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="app-container">
    <!-- Верхняя панель с датой -->
    <div class="header">
        <div class="date-display">
            <span class="time">{{ "now"|date("H:i") }}</span>
            <span class="date">{{ "now"|date("d F")|replace({'January': 'января', 'February': 'февраля', 'March': 'марта', 'April': 'апреля', 'May': 'мая', 'June': 'июня', 'July': 'июля', 'August': 'августа', 'September': 'сентября', 'October': 'октября', 'November': 'ноября', 'December': 'декабря'}) }}</span>
        </div>
        <div class="user-name">Надежда</div>
    </div>

    <!-- Расписание на день -->
    <div class="schedule">
        <div class="time-slots">
            {% for hour in 8..16 %}
            <div class="time-slot" data-hour="{{ hour }}">
                <span class="time-label">{{ "%02d:00"|format(hour) }}</span>
                <div class="slot-content" data-hour="{{ hour }}"></div>
            </div>
            {% endfor %}
        </div>

        <!-- Текущие записи -->
        <div class="current-bookings">
            {% for appointment in appointments %}
            <div class="booking-card" data-appointment-id="{{ appointment.id }}"
                 data-start="{{ appointment.startTime|date('H') }}"
                 style="top: {{ (appointment.startTime|date('H') - 8) * 60 + appointment.startTime|date('i') }}px; height: 180px;">
                <div class="booking-time">{{ appointment.startTime|date('H:i') }}-{{ appointment.endTime|date('H:i') }}</div>
                <div class="booking-client">{{ appointment.client.name }}</div>
                <div class="booking-phone">{{ appointment.client.phone }}</div>
                <button class="delete-booking" onclick="deleteAppointment({{ appointment.id }})">×</button>
            </div>
            {% endfor %}
        </div>
    </div>

    <!-- Календарь недели -->
    <div class="week-calendar">
        {% set days = ['ПН', 'ВТ', 'СР', 'ЧТ', 'ПТ', 'Сб', 'ВС'] %}
        {% set current_date = "now"|date("d")|number_format %}

        {% for i in 0..6 %}
        {% set day_date = date("now")|date_modify("+" ~ i ~ " days") %}
        <div class="calendar-day {{ day_date|date('d') == current_date ? 'active' : '' }}" data-date="{{ day_date|date('Y-m-d') }}">
            <span class="day-name">{{ days[i] }}</span>
            <span class="day-number">{{ day_date|date('d') }}</span>
        </div>
        {% endfor %}
    </div>

    <!-- Нижняя навигация -->
    <div class="bottom-nav">
        <button class="nav-item active" data-page="journal">
            <span class="icon">📋</span>
            <span>Журнал</span>
        </button>
        <button class="nav-item" data-page="schedule">
            <span class="icon">📅</span>
            <span>График</span>
        </button>
        <button class="nav-item" data-page="clients">
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

    <!-- Модальное окно для создания записи -->
    <div id="bookingModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Новая запись</h2>
            <form id="bookingForm">
                <div class="form-group">
                    <label for="client">Клиент:</label>
                    <select id="client" name="client_id" required>
                        <option value="">Выберите клиента</option>
                        {% for client in clients %}
                        <option value="{{ client.id }}">{{ client.name }} - {{ client.phone }}</option>
                        {% endfor %}
                    </select>
                </div>
                <div class="form-group">
                    <label for="date">Дата:</label>
                    <input type="date" id="date" name="date" required>
                </div>
                <div class="form-group">
                    <label for="time">Время:</label>
                    <select id="time" name="time" required>
                        {% for hour in 8..16 %}
                        <option value="{{ "%02d:00"|format(hour) }}">{{ "%02d:00"|format(hour) }}</option>
                        {% endfor %}
                    </select>
                </div>
                <button type="submit" class="btn-submit">Создать запись</button>
            </form>
        </div>
    </div>

    <!-- Модальное окно для добавления клиента -->
    <div id="clientModal" class="modal">
        <div class="modal-content">
            <span class="close-client">&times;</span>
            <h2>Новый клиент</h2>
            <form id="clientForm">
                <div class="form-group">
                    <label for="client_name">Имя:</label>
                    <input type="text" id="client_name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="client_phone">Телефон:</label>
                    <input type="tel" id="client_phone" name="phone" required pattern="\+7[0-9]{10}" placeholder="+7XXXXXXXXXX">
                </div>
                <button type="submit" class="btn-submit">Добавить клиента</button>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="{{ asset('js/booking.js') }}"></script>
</body>
</html>

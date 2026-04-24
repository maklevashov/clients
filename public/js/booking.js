$(document).ready(function() {
    let selectedHour = null;
    let selectedDate = new Date().toISOString().split('T')[0];

    // Инициализация
    $('#date').val(selectedDate);

    // Обработчик клика по временному слоту
    $('.slot-content').click(function() {
        selectedHour = $(this).data('hour');
        $('#time').val(selectedHour + ':00');
        $('#bookingModal').show();
    });

    // Закрытие модальных окон
    $('.close').click(function() {
        $('#bookingModal').hide();
    });

    $('.close-client').click(function() {
        $('#clientModal').hide();
    });

    $(window).click(function(event) {
        if ($(event.target).hasClass('modal')) {
            $('.modal').hide();
        }
    });

    // Переключение дней
    $('.calendar-day').click(function() {
        $('.calendar-day').removeClass('active');
        $(this).addClass('active');
        selectedDate = $(this).data('date');
        loadAppointments(selectedDate);
    });

    // Навигация
    $('.nav-item').click(function() {
        $('.nav-item').removeClass('active');
        $(this).addClass('active');

        const page = $(this).data('page');
        if (page === 'clients') {
            window.location.href = '/clients';
        } else if (page === 'journal') {
            window.location.href = '/';
        }
    });

    // Добавление клиента
    $('.add-client-btn').click(function() {
        $('#clientModal').show();
    });

    // Обработка формы создания записи
    $('#bookingForm').submit(function(e) {
        e.preventDefault();

        const formData = {
            client_id: $('#client').val(),
            date: $('#date').val(),
            start_time: $('#time').val()
        };

        if (!formData.client_id) {
            alert('Выберите клиента');
            return;
        }

        $.ajax({
            url: '/api/appointments',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(formData),
            success: function(response) {
                if (response.success) {
                    $('#bookingModal').hide();
                    loadAppointments(selectedDate);
                    $('#bookingForm')[0].reset();
                }
            },
            error: function(xhr) {
                alert('Ошибка при создании записи: ' + xhr.responseJSON.error);
            }
        });
    });

    // Обработка формы создания клиента
    $('#clientForm').submit(function(e) {
        e.preventDefault();

        const formData = {
            name: $('#client_name').val(),
            phone: $('#client_phone').val()
        };

        $.ajax({
            url: '/api/clients',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(formData),
            success: function(response) {
                if (response.success) {
                    $('#clientModal').hide();
                    // Добавляем клиента в выпадающий список
                    $('#client').append(`<option value="${response.id}">${response.name} - ${response.phone}</option>`);
                    $('#clientForm')[0].reset();
                }
            },
            error: function(xhr) {
                alert('Ошибка при добавлении клиента: ' + xhr.responseJSON.error);
            }
        });
    });
});

// Функция загрузки записей
function loadAppointments(date) {
    $.ajax({
        url: '/api/appointments?date=' + date,
        method: 'GET',
        success: function(appointments) {
            updateScheduleDisplay(appointments);
        }
    });
}

// Функция обновления отображения расписания
function updateScheduleDisplay(appointments) {
    $('.booking-card').remove();

    appointments.forEach(function(appointment) {
        const hour = parseInt(appointment.start_time.split(':')[0]);
        const topPosition = (hour - 8) * 60;

        const card = `
            <div class="booking-card" data-appointment-id="${appointment.id}" 
                 style="top: ${topPosition}px; height: 180px;">
                <div class="booking-time">${appointment.start_time}-${appointment.end_time}</div>
                <div class="booking-client">${appointment.client_name}</div>
                <div class="booking-phone">${appointment.client_phone}</div>
                <button class="delete-booking" onclick="deleteAppointment(${appointment.id})">×</button>
            </div>
        `;

        $('.current-bookings').append(card);
    });
}

// Функция удаления записи
function deleteAppointment(id) {
    if (confirm('Удалить эту запись?')) {
        $.ajax({
            url: '/api/appointments/' + id,
            method: 'DELETE',
            success: function(response) {
                if (response.success) {
                    $(`.booking-card[data-appointment-id="${id}"]`).remove();
                }
            }
        });
    }
}
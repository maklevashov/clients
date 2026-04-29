// main.js - основной файл инициализации
$(document).ready(function() {
    // Инициализация календаря недели со свайпами
    if (typeof initWeekCalendarWithSwipe === 'function') {
        initWeekCalendarWithSwipe();
    }

    // Обработчики кнопок навигации
    $('#prevWeekBtn').click(function(e) {
        e.stopPropagation();
        if (typeof prevWeek === 'function') {
            prevWeek();
        }
    });

    $('#nextWeekBtn').click(function(e) {
        e.stopPropagation();
        if (typeof nextWeek === 'function') {
            nextWeek();
        }
    });

    $('#todayBtn').click(function(e) {
        e.stopPropagation();
        if (typeof goToCurrentWeek === 'function') {
            goToCurrentWeek();
        }
    });
});
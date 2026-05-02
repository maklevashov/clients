// ClientSelector.js - модуль для выбора клиента
class ClientSelector {
    constructor() {
        this.selectedCallback = null;
        this.searchTimeout = null;
        this.currentAppointmentData = null;
        this.init();
    }

    init() {
        this.bindEvents();
    }

    bindEvents() {
        // Поиск клиента
        $('#clientSearchInput').on('input', (e) => {
            this.handleSearch(e.target.value);
        });

        // Кнопка очистки
        $('#clearSearchBtn').click(() => {
            $('#clientSearchInput').val('').focus();
            this.handleSearch('');
        });

        // Открытие формы создания клиента
        $('#openCreateClientBtn').click(() => {
            this.closeSelectionModal();
            this.openCreateClientModal();
        });

        $('#createFromSearchBtn').click(() => {
            const searchTerm = $('#clientSearchInput').val();
            this.closeSelectionModal();
            this.openCreateClientModalWithData(searchTerm);
        });

        // Закрытие по клику вне области
        $(window).click((e) => {
            if ($(e.target).hasClass('modal')) {
                this.closeAllModals();
            }
        });
    }

    handleSearch(query) {
        if (this.searchTimeout) {
            clearTimeout(this.searchTimeout);
        }

        if (query.length < 2) {
            if (query.length === 0) {
                this.loadRecentClients();
            } else {
                $('#searchResults .search-loading').hide();
                $('#searchResults .no-results').hide();
                $('#clientsListModal').html('<div class="no-results" style="display:block;"><p>Введите минимум 2 символа для поиска</p></div>');
                $('#clearSearchBtn').toggle(query.length > 0);
            }
            return;
        }

        $('#searchResults .search-loading').show();
        $('#clientsListModal').hide();
        $('#searchResults .no-results').hide();
        $('#clearSearchBtn').show();

        this.searchTimeout = setTimeout(() => {
            this.performSearch(query);
        }, 300);
    }

    performSearch(query) {
        $.ajax({
            url: `/api/clients/search?q=${encodeURIComponent(query)}`,
            method: 'GET',
            success: (clients) => {
                $('#searchResults .search-loading').hide();
                this.displayResults(clients, query);
            },
            error: () => {
                $('#searchResults .search-loading').hide();
                $('#clientsListModal').html('<div class="no-results"><p>Ошибка поиска</p></div>').show();
            }
        });
    }

    loadRecentClients() {
        $.ajax({
            url: '/api/clients/recent',
            method: 'GET',
            success: (clients) => {
                this.displayResults(clients, '');
            }
        });
    }

    displayResults(clients, searchTerm) {
        const $container = $('#clientsListModal');

        if (clients.length === 0) {
            $container.hide();
            $('#searchResults .no-results').show();
            if (searchTerm && searchTerm.length >= 2) {
                $('#createFromSearchBtn').show();
            } else {
                $('#createFromSearchBtn').hide();
            }
            return;
        }

        $('#searchResults .no-results').hide();
        $container.show();

        let html = '';
        clients.forEach(client => {
            const hasEmail = client.email && client.email !== 'Неизвестно';
            html += `
                <div class="client-result-item" data-client-id="${client.id}">
                    <div class="client-result-info">
                        <div class="client-result-name">${this.escapeHtml(client.name)}</div>
                        <div class="client-result-phone">${client.phone}</div>
                        ${hasEmail ? `<div class="client-result-email">${this.escapeHtml(client.email)}</div>` : ''}
                    </div>
                    <button class="select-client-btn" onclick="clientSelector.selectClient(${client.id})">Выбрать</button>
                </div>
            `;
        });

        $container.html(html);
    }

    selectClient(clientId) {
        $.ajax({
            url: `/api/clients/${clientId}`,
            method: 'GET',
            success: (client) => {
                if (this.selectedCallback) {
                    this.selectedCallback(client);
                }
                this.closeAllModals();
            }
        });
    }

    openCreateClientModal() {
        $('#newClientName').val('');
        $('#newClientPhone').val('');
        $('#newClientEmail').val('');
        $('#newClientNote').val('');
        $('#createClientModal').addClass('show');
    }

    openCreateClientModalWithData(searchTerm) {
        // Если поиск был по телефону или email, подставляем
        if (searchTerm && searchTerm.includes('@')) {
            $('#newClientEmail').val(searchTerm);
        } else if (searchTerm && searchTerm.match(/[\d\+]/)) {
            $('#newClientPhone').val(searchTerm);
        } else if (searchTerm) {
            $('#newClientName').val(searchTerm);
        }
        $('#createClientModal').addClass('show');
    }

    createAndSelectClient() {
        const name = $('#newClientName').val().trim();
        const phone = $('#newClientPhone').val().trim();
        const email = $('#newClientEmail').val().trim();
        const note = $('#newClientNote').val().trim();

        if (!name || !phone) {
            alert('Пожалуйста, заполните имя и телефон клиента');
            return;
        }

        const clientData = {
            name: name,
            phone: phone,
            email: email || null,
            note: note || null
        };

        $.ajax({
            url: '/api/clients',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(clientData),
            success: (response) => {
                if (response.success && response.id) {
                    const newClient = {
                        id: response.id,
                        name: name,
                        phone: phone,
                        email: email
                    };
                    if (this.selectedCallback) {
                        this.selectedCallback(newClient);
                    }
                    this.closeAllModals();
                }
            },
            error: (xhr) => {
                alert('Ошибка при создании клиента: ' + (xhr.responseJSON?.error || 'Неизвестная ошибка'));
            }
        });
    }

    show(callback, appointmentData = null) {
        this.selectedCallback = callback;
        this.currentAppointmentData = appointmentData;
        $('#clientSearchInput').val('');
        this.loadRecentClients();
        $('#clientSelectionModal').addClass('show');
        setTimeout(() => $('#clientSearchInput').focus(), 300);
    }

    closeSelectionModal() {
        $('#clientSelectionModal').removeClass('show');
        $('#clientSearchInput').val('');
        $('#searchResults .search-loading').hide();
        $('#searchResults .no-results').hide();
        $('#clearSearchBtn').hide();
    }

    closeCreateClientModal() {
        $('#createClientModal').removeClass('show');
    }

    closeAllModals() {
        this.closeSelectionModal();
        this.closeCreateClientModal();
    }

    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Инициализация глобального экземпляра
const clientSelector = new ClientSelector();

// Функции для вызова из HTML
window.openClientSelector = function(callback, appointmentData = null) {
    clientSelector.show(callback, appointmentData);
};

window.selectClient = function(clientId) {
    clientSelector.selectClient(clientId);
};

window.createAndSelectClient = function() {
    clientSelector.createAndSelectClient();
};

window.closeSelectionModal = function() {
    clientSelector.closeSelectionModal();
};

window.closeCreateClientModal = function() {
    clientSelector.closeCreateClientModal();
};
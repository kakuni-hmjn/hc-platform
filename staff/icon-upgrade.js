(() => {
    'use strict';

    const aliases = {
        account_circle: 'user', manage_accounts: 'users', group: 'users', groups: 'users',
        person_add: 'user-plus', person_off: 'user-x', badge: 'user',
        admin_panel_settings: 'shield', verified_user: 'shield', approval: 'check-circle',
        check: 'check', check_circle: 'check-circle', task_alt: 'check-circle', done_all: 'check-circle',
        home: 'home', dashboard: 'grid', space_dashboard: 'grid', dashboard_customize: 'grid',
        apps: 'grid', workspaces: 'grid', category: 'grid', view_list: 'list', view_stream: 'list',
        menu_open: 'menu', settings: 'settings', settings_suggest: 'settings', build_circle: 'settings',
        construction: 'settings', tune: 'sliders', palette: 'palette', search: 'search',
        search_off: 'search', filter_alt: 'filter', sort: 'sort', close: 'close', logout: 'logout',
        arrow_back: 'arrow-left', arrow_forward: 'arrow-right', arrow_upward: 'arrow-up',
        arrow_downward: 'arrow-down', chevron_right: 'chevron-right', chevron_left: 'chevron-left',
        expand_more: 'chevron-down', expand_less: 'chevron-up', open_in_new: 'external-link',
        add: 'plus', add_box: 'plus-square', add_link: 'link', link: 'link', sync: 'refresh',
        restart_alt: 'refresh', swap_horiz: 'swap', notifications: 'bell',
        notifications_active: 'bell', notification_important: 'bell', campaign: 'megaphone',
        mail: 'mail', reply: 'reply', send: 'send', inbox: 'inbox', forum: 'message',
        support_agent: 'headphones', edit_note: 'edit', description: 'file', newspaper: 'file',
        receipt_long: 'receipt', request_quote: 'receipt', assignment: 'clipboard',
        schedule: 'history', visibility: 'eye', language: 'globe', domain: 'building',
        location_on: 'pin', location_off: 'pin', web_asset: 'monitor', computer: 'monitor',
        monitoring: 'activity', monitor_heart: 'activity', dns: 'server', storage: 'database',
        database: 'database', memory: 'cpu', precision_manufacturing: 'cpu', hub: 'network',
        account_tree: 'network', terminal: 'terminal', code: 'code', rocket_launch: 'rocket',
        play_circle: 'play', sports_esports: 'gamepad', inventory_2: 'box', warehouse: 'building',
        shopping_bag: 'bag', sell: 'tag', tag: 'tag', payments: 'credit-card',
        production_quantity_limits: 'bag', qr_code_2: 'qr', qr_code_scanner: 'qr',
        lock: 'lock', key: 'key', info: 'info', error: 'alert', error_outline: 'alert',
        warning: 'alert', history: 'history', add_photo_alternate: 'image', delete: 'trash', save: 'save'
    };

    const paths = {
        circle: '<circle cx="12" cy="12" r="8"/>',
        user: '<circle cx="12" cy="8" r="3.5"/><path d="M4.5 20c.8-4.1 3.3-6 7.5-6s6.7 1.9 7.5 6"/>',
        users: '<path d="M16 20c-.5-3.3-2.3-5-5.5-5S5.5 16.7 5 20"/><circle cx="10.5" cy="8.5" r="3.5"/><path d="M16 5.2a3.3 3.3 0 0 1 0 6.3M17 14.5c1.8.7 2.8 2.5 3 5.5"/>',
        'user-plus': '<circle cx="9" cy="8" r="3.5"/><path d="M3.5 20c.6-4 2.4-6 5.5-6s4.9 2 5.5 6M18 8v6M15 11h6"/>',
        'user-x': '<circle cx="9" cy="8" r="3.5"/><path d="M3.5 20c.6-4 2.4-6 5.5-6 1.3 0 2.4.3 3.2.8M16 15l5 5M21 15l-5 5"/>',
        shield: '<path d="M12 3l7 3v5c0 4.7-2.4 7.7-7 10-4.6-2.3-7-5.3-7-10V6l7-3z"/><path d="M8.7 12l2.1 2.1 4.6-4.6"/>',
        check: '<path d="M5 12.5l4.2 4.2L19 7"/>',
        'check-circle': '<circle cx="12" cy="12" r="9"/><path d="M7.5 12.3l3 3L17 8.8"/>',
        home: '<path d="M3.5 11L12 3.8 20.5 11"/><path d="M5.5 9.5V21h13V9.5M9.5 21v-6h5v6"/>',
        grid: '<rect x="3.5" y="3.5" width="7" height="7" rx="1.2"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.2"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.2"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.2"/>',
        list: '<path d="M9 6h12M9 12h12M9 18h12"/><circle cx="4.5" cy="6" r="1"/><circle cx="4.5" cy="12" r="1"/><circle cx="4.5" cy="18" r="1"/>',
        menu: '<path d="M4 6h16M4 12h16M4 18h10"/>',
        settings: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3A1.7 1.7 0 0 0 10 3V2.8h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1z"/>',
        sliders: '<path d="M4 6h5M15 6h5M4 12h10M18 12h2M4 18h2M12 18h8"/><circle cx="12" cy="6" r="2"/><circle cx="16" cy="12" r="2"/><circle cx="9" cy="18" r="2"/>',
        palette: '<path d="M12 3a9 9 0 0 0 0 18h1.3a1.8 1.8 0 0 0 1.1-3.2 1.8 1.8 0 0 1 1.1-3.2H18a3 3 0 0 0 3-3C21 6.7 17 3 12 3z"/><circle cx="7.5" cy="10" r="1"/><circle cx="10" cy="6.5" r="1"/><circle cx="14.5" cy="6.8" r="1"/>',
        search: '<circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5L21 21"/>',
        filter: '<path d="M3 5h18l-7 8v6l-4 2v-8L3 5z"/>',
        sort: '<path d="M7 4v16M4 7l3-3 3 3M17 20V4M14 17l3 3 3-3"/>',
        close: '<path d="M5 5l14 14M19 5L5 19"/>',
        logout: '<path d="M10 4H5v16h5M14 8l4 4-4 4M9 12h9"/>',
        'arrow-left': '<path d="M19 12H5M11 6l-6 6 6 6"/>',
        'arrow-right': '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'arrow-up': '<path d="M12 19V5M6 11l6-6 6 6"/>',
        'arrow-down': '<path d="M12 5v14M6 13l6 6 6-6"/>',
        'chevron-right': '<path d="M9 5l7 7-7 7"/>',
        'chevron-left': '<path d="M15 5l-7 7 7 7"/>',
        'chevron-down': '<path d="M5 9l7 7 7-7"/>',
        'chevron-up': '<path d="M5 15l7-7 7 7"/>',
        'external-link': '<path d="M14 4h6v6M20 4l-9 9"/><path d="M18 13v7H4V6h7"/>',
        plus: '<path d="M12 5v14M5 12h14"/>',
        'plus-square': '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M12 7v10M7 12h10"/>',
        link: '<path d="M10 14l4-4M8.5 17.5l-1 1a3.5 3.5 0 0 1-5-5l3-3a3.5 3.5 0 0 1 5 0M15.5 6.5l1-1a3.5 3.5 0 0 1 5 5l-3 3a3.5 3.5 0 0 1-5 0"/>',
        refresh: '<path d="M20 7v5h-5M4 17v-5h5"/><path d="M6.1 8A7 7 0 0 1 18.8 7M17.9 16A7 7 0 0 1 5.2 17"/>',
        swap: '<path d="M4 8h13M14 5l3 3-3 3M20 16H7M10 13l-3 3 3 3"/>',
        bell: '<path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9zM10 21h4"/>',
        megaphone: '<path d="M4 10v4l12 4V6L4 10zM4 10H2v4h2M7 15l1 5h3"/>',
        mail: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M4 7l8 6 8-6"/>',
        reply: '<path d="M9 8l-6 5 6 5v-3c6 0 9 1.5 12 5-1-7-5-10-12-10V8z"/>',
        send: '<path d="M3 4l18 8-18 8 3-8-3-8zM6 12h15"/>',
        inbox: '<path d="M4 4h16l2 11v5H2v-5L4 4z"/><path d="M2 15h6l2 3h4l2-3h6"/>',
        message: '<path d="M4 4h16v13H9l-5 4V4z"/><path d="M8 9h8M8 13h5"/>',
        headphones: '<path d="M4 14v-2a8 8 0 0 1 16 0v2"/><rect x="3" y="13" width="4" height="7" rx="2"/><rect x="17" y="13" width="4" height="7" rx="2"/>',
        edit: '<path d="M4 20l4.5-1 11-11-3.5-3.5-11 11L4 20zM14.5 6l3.5 3.5M4 20h16"/>',
        file: '<path d="M6 3h8l4 4v14H6V3z"/><path d="M14 3v5h5M9 12h6M9 16h6"/>',
        receipt: '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3z"/><path d="M9 8h6M9 12h6M9 16h4"/>',
        clipboard: '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V2h6v2M9 9h6M9 13h6M9 17h4"/>',
        eye: '<path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6z"/><circle cx="12" cy="12" r="2.7"/>',
        globe: '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
        building: '<path d="M4 21V5h10v16M14 9h6v12M8 9h2M8 13h2M8 17h2M17 13h1M17 17h1M2 21h20"/>',
        pin: '<path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0z"/><circle cx="12" cy="10" r="2.5"/>',
        monitor: '<rect x="3" y="4" width="18" height="13" rx="2"/><path d="M8 21h8M12 17v4"/>',
        activity: '<path d="M3 12h4l2.5-6 5 12 2.5-6h4"/>',
        server: '<rect x="3" y="4" width="18" height="6" rx="1.5"/><rect x="3" y="14" width="18" height="6" rx="1.5"/><path d="M7 7h.01M7 17h.01M11 7h7M11 17h7"/>',
        database: '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v7c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 12v7c0 1.7 3.6 3 8 3s8-1.3 8-3v-7"/>',
        cpu: '<rect x="6" y="6" width="12" height="12" rx="2"/><rect x="9" y="9" width="6" height="6"/><path d="M9 2v4M15 2v4M9 18v4M15 18v4M2 9h4M2 15h4M18 9h4M18 15h4"/>',
        network: '<rect x="9" y="3" width="6" height="5" rx="1"/><rect x="3" y="16" width="6" height="5" rx="1"/><rect x="15" y="16" width="6" height="5" rx="1"/><path d="M12 8v4M6 16v-4h12v4"/>',
        terminal: '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 9l3 3-3 3M13 16h4"/>',
        code: '<path d="M8 5l-6 7 6 7M16 5l6 7-6 7M14 3l-4 18"/>',
        rocket: '<path d="M14 4c3-2 6-1 6-1s1 3-1 6l-7 7-4-4 6-8zM8 12l-4 1-2 3 6 1M12 16l-1 6 3-2 1-4"/><circle cx="16" cy="7" r="1"/>',
        play: '<circle cx="12" cy="12" r="9"/><path d="M10 8l6 4-6 4V8z"/>',
        gamepad: '<path d="M7 8h10c2.5 0 4 2 4 5l-1 5c-.3 1.5-2 2-3 1l-2-2H9l-2 2c-1 1-2.7.5-3-1l-1-5c0-3 1.5-5 4-5z"/><path d="M7 11v4M5 13h4M16 12h.01M18 14h.01"/>',
        box: '<path d="M4 7l8-4 8 4v10l-8 4-8-4V7zM4 7l8 4 8-4M12 11v10"/>',
        bag: '<path d="M5 8h14l1 13H4L5 8zM9 8V6a3 3 0 0 1 6 0v2"/>',
        tag: '<path d="M3 12V4h8l10 10-7 7L3 12z"/><circle cx="7.5" cy="8" r="1.2"/>',
        'credit-card': '<rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="M3 9h18M7 15h3"/>',
        qr: '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h3v3h-3zM18 18h3v3h-3zM18 14h3M14 19v2"/>',
        lock: '<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/>',
        key: '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M16 7l2 2M14 9l2 2"/>',
        info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7h.01"/>',
        alert: '<path d="M12 3l10 18H2L12 3z"/><path d="M12 9v5M12 18h.01"/>',
        history: '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/>',
        image: '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="9" r="2"/><path d="M4 17l5-5 4 4 2-2 5 5"/>',
        trash: '<path d="M4 7h16M9 7V4h6v3M7 7l1 14h8l1-14M10 11v6M14 11v6"/>',
        save: '<path d="M4 3h14l2 2v16H4V3z"/><path d="M8 3v6h8V3M8 21v-7h8v7"/>'
    };

    const iconBody = (name) => paths[aliases[name] || name] || paths.circle;

    const setSvgIcon = (svg, name) => {
        const cleanName = String(name || 'circle').trim() || 'circle';
        svg.dataset.icon = cleanName;
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('fill', 'none');
        svg.setAttribute('stroke', 'currentColor');
        svg.setAttribute('stroke-width', '1.9');
        svg.setAttribute('stroke-linecap', 'round');
        svg.setAttribute('stroke-linejoin', 'round');
        svg.setAttribute('aria-hidden', 'true');
        svg.setAttribute('focusable', 'false');
        svg.innerHTML = iconBody(cleanName);
        return svg;
    };

    const upgrade = (element) => {
        if (!(element instanceof HTMLElement) || !element.matches('span.material-icons')) return element;
        const name = element.dataset.icon || element.textContent.trim() || 'circle';
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        Array.from(element.attributes).forEach((attribute) => svg.setAttribute(attribute.name, attribute.value));
        svg.classList.add('staff-svg-icon');
        if (!svg.style.width) svg.style.width = '1em';
        if (!svg.style.height) svg.style.height = '1em';
        setSvgIcon(svg, name);
        element.replaceWith(svg);
        return svg;
    };

    const upgradeTree = (root) => {
        if (root instanceof HTMLElement && root.matches('span.material-icons')) upgrade(root);
        if (root instanceof Element || root instanceof Document || root instanceof DocumentFragment) {
            root.querySelectorAll('span.material-icons').forEach(upgrade);
        }
    };

    window.staffSetIcon = (element, name) => {
        if (element instanceof SVGElement && element.classList.contains('material-icons')) {
            return setSvgIcon(element, name);
        }
        if (element instanceof HTMLElement) {
            element.textContent = name;
            return upgrade(element);
        }
        return element;
    };

    upgradeTree(document);
    new MutationObserver((mutations) => {
        mutations.forEach((mutation) => mutation.addedNodes.forEach((node) => upgradeTree(node)));
    }).observe(document.documentElement, { childList: true, subtree: true });
})();

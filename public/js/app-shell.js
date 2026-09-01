(function () {
    const path = window.location.pathname;
    if (path.startsWith('/admin') || path === '/widget' || new URLSearchParams(window.location.search).get('embedded') === '1') return;
    const finalStyles = document.createElement('link');
    finalStyles.rel = 'stylesheet';
    finalStyles.href = '/css/app-shell.css?v=2';
    document.head.appendChild(finalStyles);
    const items = [['/','⌂','Home'],['/stopList','●','Fermate'],['/route-finder','⇄','Percorsi']];
    const more = [['/lines-map','Mappa linee'],['/line-schedule','Orari delle linee'],['/live-map','Bus in servizio'],['/delay-stats','Statistiche ritardi'],['/delete-cookie','Privacy e dati']];
    const active = target => target === '/' ? path === '/' : path === target || path.startsWith(target + '/');
    const nav = document.createElement('nav'); nav.className = 'actv-bottom-nav'; nav.setAttribute('aria-label','Navigazione principale');
    nav.innerHTML = items.map(([href,icon,label]) => `<a href="${href}" class="${active(href) ? 'active' : ''}"><span class="nav-icon" aria-hidden="true">${icon}</span><span>${label}</span></a>`).join('') + '<a href="#actv-more" id="actv-more-toggle" aria-expanded="false"><span class="nav-icon" aria-hidden="true">⋯</span><span>Altro</span></a>';
    document.body.appendChild(nav);
    const menu = document.createElement('div'); menu.className = 'actv-more-menu'; menu.id = 'actv-more'; menu.setAttribute('aria-label','Altre funzioni'); menu.innerHTML = more.map(([href,label]) => `<a href="${href}">${label}</a>`).join(''); document.body.appendChild(menu);
    const toggle = document.getElementById('actv-more-toggle'); toggle.addEventListener('click', event => { event.preventDefault(); const open = menu.classList.toggle('open'); toggle.setAttribute('aria-expanded',String(open)); });
    document.addEventListener('click', event => { if (!menu.contains(event.target) && !toggle.contains(event.target)) { menu.classList.remove('open'); toggle.setAttribute('aria-expanded','false'); } });
})();

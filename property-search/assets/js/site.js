(() => {
  const config = window.HomeSearch || {};
  const money = value => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 0 }).format(Number(value) || 0);
  const escape = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]);
  const read = key => { try { return JSON.parse(localStorage.getItem(key) || '[]'); } catch { return []; } };
  const save = (key, value) => { try { localStorage.setItem(key, JSON.stringify(value)); } catch { /* Browsing still works when storage is unavailable. */ } };
  let favorites = read('home-search-favorites');
  let compare = read('home-search-compare');
  if (!Array.isArray(favorites)) favorites = [];
  if (!Array.isArray(compare)) compare = [];
  favorites = [...new Set(favorites.map(Number).filter(id => Number.isInteger(id) && id > 0))];
  compare = [...new Set(compare.map(Number).filter(id => Number.isInteger(id) && id > 0))].slice(0, 3);

  const toast = message => {
    let node = document.getElementById('home-search-toast');
    if (!node) { node = document.createElement('div'); node.id = 'home-search-toast'; node.className = 'toast'; node.setAttribute('role', 'status'); document.body.append(node); }
    node.textContent = message;
    node.hidden = false;
    clearTimeout(node.timer);
    node.timer = setTimeout(() => { node.hidden = true; }, 3500);
  };
  const sync = () => {
    document.querySelectorAll('[data-favorite-count]').forEach(node => node.textContent = favorites.length);
    document.querySelectorAll('[data-compare-count]').forEach(node => node.textContent = compare.length);
    document.querySelectorAll('[data-favorite]').forEach(button => {
      const active = favorites.includes(Number(button.dataset.favorite));
      button.setAttribute('aria-pressed', active);
      button.textContent = active ? '♥' : '♡';
    });
    document.querySelectorAll('[data-compare]').forEach(input => { input.checked = compare.includes(Number(input.dataset.compare)); });
  };
  sync();

  document.addEventListener('click', event => {
    const button = event.target.closest('[data-favorite]');
    if (!button) return;
    const id = Number(button.dataset.favorite);
    favorites = favorites.includes(id) ? favorites.filter(value => value !== id) : [...favorites, id];
    save('home-search-favorites', favorites);
    sync();
    if (!favorites.includes(id) && document.getElementById('favorites-content')) {
      button.closest('.property-card').remove();
      if (!document.querySelector('#favorites-content .property-card')) document.getElementById('favorites-content').innerHTML = '<div class="empty-state"><h3>No saved homes yet.</h3><p>Tap the heart on a property to keep it here.</p></div>';
    }
    toast(favorites.includes(id) ? 'Home saved.' : 'Home removed from saved homes.');
  });
  document.addEventListener('click', event => {
    const button = event.target.closest('[data-remove-compare]');
    if (!button) return;
    compare = compare.filter(id => id !== Number(button.dataset.removeCompare));
    save('home-search-compare', compare);
    location.reload();
  });
  document.addEventListener('change', event => {
    if (!event.target.matches('[data-compare]')) return;
    const id = Number(event.target.dataset.compare);
    if (event.target.checked && compare.length >= 3) { event.target.checked = false; toast('Compare up to three homes at a time.'); return; }
    compare = event.target.checked ? [...compare, id] : compare.filter(value => value !== id);
    save('home-search-compare', compare);
    sync();
  });

  const menu = document.querySelector('.menu-toggle');
  if (menu) menu.addEventListener('click', () => {
    const open = menu.getAttribute('aria-expanded') !== 'true';
    menu.setAttribute('aria-expanded', String(open));
    menu.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    document.getElementById('site-nav').classList.toggle('open', open);
  });

  const card = item => `<article class="property-card" data-property-id="${Number(item.id)}"><a class="card-media" href="${escape(item.url)}">${item.image ? `<img src="${escape(item.image)}" alt="${escape(item.title)}" loading="lazy">` : '<span class="property-placeholder">Photo coming soon</span>'}<span class="card-badge">${escape(item.type)}</span></a><button class="favorite-button" type="button" data-favorite="${Number(item.id)}" aria-label="Save ${escape(item.title)}" aria-pressed="false">♡</button><div class="card-body"><p class="card-price">${money(item.price)}</p><h3><a href="${escape(item.url)}">${escape(item.title)}</a></h3><p class="card-location">${escape(item.location)}</p><div class="card-meta"><span>${Number(item.beds)} beds</span><span>${Number(item.baths)} baths</span><span>${Number(item.area)} sqm</span></div><div class="card-actions"><label class="compare-control"><input type="checkbox" data-compare="${Number(item.id)}"> Compare</label><a class="text-link" href="${escape(item.url)}">View details ↗</a></div></div></article>`;
  const fetchProperties = async params => {
    const response = await fetch(`${config.searchUrl}?${params.toString()}`);
    if (!response.ok) throw new Error('Could not load properties.');
    return response.json();
  };

  const filters = document.getElementById('property-filters');
  if (filters && config.searchUrl) {
    const results = document.getElementById('property-results');
    const count = document.getElementById('result-count');
    const loadMore = document.getElementById('load-more');
    const mapPanel = document.getElementById('property-map');
    const layout = document.getElementById('results-layout');
    let page = 1, pages = 1, current = [], map, markers, requestId = 0;
    const params = () => new URLSearchParams(new FormData(filters));
    const renderMap = () => {
      if (mapPanel.hidden) return;
      if (!window.L) { mapPanel.textContent = 'Map is temporarily unavailable.'; return; }
      if (!map) {
        map = L.map(mapPanel, { scrollWheelZoom: false }).setView([12.8797, 121.774], 6);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors', maxZoom: 19 }).addTo(map);
        markers = L.layerGroup().addTo(map);
      }
      markers.clearLayers();
      const bounds = [];
      current.forEach(item => {
        if (item.lat === null || item.lng === null) return;
        const pin = L.marker([item.lat, item.lng]).addTo(markers);
        pin.bindPopup(`<strong>${escape(item.title)}</strong><br>${money(item.price)}<br><a href="${escape(item.url)}">View property</a>`);
        bounds.push([item.lat, item.lng]);
      });
      setTimeout(() => map.invalidateSize(), 0);
      if (bounds.length) map.fitBounds(bounds, { padding: [35, 35], maxZoom: 14 });
    };
    const search = async (nextPage = 1, updateUrl = true) => {
      const thisRequest = ++requestId;
      const query = params(); query.set('page', nextPage);
      if (Number(query.get('min_price')) > Number(query.get('max_price')) && Number(query.get('max_price')) > 0) { count.textContent = 'Minimum price must be below maximum price.'; return; }
      count.textContent = 'Searching homes…';
      try {
        const data = await fetchProperties(query);
        if (thisRequest !== requestId) return;
        if (nextPage === 1) { current = data.items; results.innerHTML = data.items.length ? data.items.map(card).join('') : '<div class="empty-state"><h3>No homes found.</h3><p>Try a different location or a wider price range.</p></div>'; }
        else { current.push(...data.items); results.insertAdjacentHTML('beforeend', data.items.map(card).join('')); }
        page = nextPage; pages = data.pages;
        document.getElementById('server-pagination').hidden = true;
        document.querySelector('.view-switch').classList.add('ready');
        loadMore.hidden = page >= pages;
        count.textContent = `${data.total} ${data.total === 1 ? 'home' : 'homes'} found`;
        sync(); renderMap();
        if (updateUrl) { query.delete('page'); history.replaceState(null, '', `${filters.action}?${query.toString()}`); }
      } catch { if (thisRequest === requestId) { count.textContent = 'Search is unavailable. Please try again.'; loadMore.hidden = true; } }
    };
    filters.addEventListener('submit', event => { event.preventDefault(); search(); });
    filters.querySelectorAll('select').forEach(field => field.addEventListener('change', () => search()));
    let timer;
    filters.querySelectorAll('input').forEach(field => field.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => search(), 450); }));
    loadMore.addEventListener('click', () => search(page + 1, false));
    document.querySelectorAll('[data-view]').forEach(button => button.addEventListener('click', () => {
      const showMap = button.dataset.view === 'map';
      mapPanel.hidden = !showMap;
      layout.classList.toggle('map-open', showMap);
      document.querySelectorAll('[data-view]').forEach(other => other.setAttribute('aria-pressed', String(other === button)));
      renderMap();
    }));
    search(1, false);
  }

  const calculator = document.querySelector('.calculator');
  if (calculator) {
    const output = document.getElementById('monthly-payment');
    const calculate = () => {
      const down = Number(document.getElementById('down-payment').value);
      const rate = Number(document.getElementById('interest-rate').value);
      const years = Number(document.getElementById('loan-years').value);
      if (down < 0 || down >= 100 || rate < 0 || rate > 100 || years < 1 || years > 40) { output.textContent = 'Enter valid loan terms'; return; }
      const principal = Number(calculator.dataset.price) * (1 - down / 100);
      const months = years * 12, monthlyRate = rate / 1200;
      const payment = monthlyRate ? principal * monthlyRate / (1 - Math.pow(1 + monthlyRate, -months)) : principal / months;
      output.textContent = money(payment);
    };
    calculator.querySelectorAll('input').forEach(input => input.addEventListener('input', calculate));
    calculate();
  }

  const favoritesNode = document.getElementById('favorites-content');
  const compareNode = document.getElementById('compare-content');
  if ((favoritesNode || compareNode) && config.searchUrl) {
    const ids = favoritesNode ? favorites : compare;
    const node = favoritesNode || compareNode;
    if (ids.length) fetchProperties(new URLSearchParams({ include: ids.join(',') })).then(data => {
      const items = ids.map(id => data.items.find(item => item.id === id)).filter(Boolean);
      if (!items.length) return;
      if (favoritesNode) node.innerHTML = items.map(card).join('');
      else node.innerHTML = `<div class="compare-scroll"><table class="compare-table"><tbody><tr><th scope="row">Property</th>${items.map(item => `<td><a class="text-link" href="${escape(item.url)}">${escape(item.title)}</a><br><button class="text-link" type="button" data-remove-compare="${Number(item.id)}">Remove</button></td>`).join('')}</tr><tr><th scope="row">Price</th>${items.map(item => `<td>${money(item.price)}</td>`).join('')}</tr><tr><th scope="row">Location</th>${items.map(item => `<td>${escape(item.location)}</td>`).join('')}</tr><tr><th scope="row">Type</th>${items.map(item => `<td>${escape(item.type)}</td>`).join('')}</tr><tr><th scope="row">Bedrooms</th>${items.map(item => `<td>${Number(item.beds)}</td>`).join('')}</tr><tr><th scope="row">Bathrooms</th>${items.map(item => `<td>${Number(item.baths)}</td>`).join('')}</tr><tr><th scope="row">Area</th>${items.map(item => `<td>${Number(item.area)} sqm</td>`).join('')}</tr></tbody></table></div>`;
      sync();
    }).catch(() => { node.innerHTML = '<p class="notice">Saved properties are temporarily unavailable. Please try again later.</p>'; });
  }
})();

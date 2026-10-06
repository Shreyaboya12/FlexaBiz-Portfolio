(() => {
  const modules = {
    dashboard: { label: 'Dashboard', endpoint: null },
    hero: {
      label: 'Hero',
      endpoint: 'hero',
      fields: [
        { name: 'title', label: 'Title', required: true },
        { name: 'subtitle', label: 'Subtitle', type: 'textarea', full: true },
        { name: 'image_url', label: 'Image URL', type: 'url' },
        { name: 'video_url', label: 'Video URL', type: 'url' },
        { name: 'display_order', label: 'Display order', type: 'number', default: 1 },
        { name: 'featured', label: 'Featured', type: 'checkbox' },
        { name: 'active', label: 'Active', type: 'checkbox', checked: true },
      ],
      columns: ['title', 'display_order', 'featured', 'active'],
    },
    videos: {
      label: 'Videos',
      endpoint: 'videos',
      fields: [
        { name: 'title', label: 'Title', required: true },
        { name: 'category', label: 'Category', type: 'select', required: true, options: ['Product Shoot', 'Brand Video', 'Advertisement', 'Social Media', 'Commercial', 'Other'] },
        { name: 'description', label: 'Description', type: 'textarea', full: true },
        { name: 'video_type', label: 'Video type', type: 'select', default: 'direct', options: ['youtube', 'vimeo', 'direct', 'other'] },
        { name: 'video', label: 'Video file', type: 'file', accept: '.mp4,.webm,.mov', currentField: 'video_url', maxBytes: 100 * 1024 * 1024, required: true },
        { name: 'display_order', label: 'Display order', type: 'number', default: 1 },
        { name: 'featured', label: 'Featured', type: 'checkbox' },
        { name: 'active', label: 'Active', type: 'checkbox', checked: true },
      ],
      columns: ['title', 'category', 'display_order', 'featured', 'active'],
    },
    services: {
      label: 'Services',
      endpoint: 'services',
      fields: [
        { name: 'name', label: 'Service name', required: true },
        { name: 'short_description', label: 'Short description', type: 'textarea', full: true },
        { name: 'full_description', label: 'Full description', type: 'textarea', full: true },
        { name: 'image', label: 'Service image', type: 'file', accept: '.jpg,.jpeg,.png,.webp', currentField: 'image_url', maxBytes: 10 * 1024 * 1024 },
        { name: 'display_order', label: 'Display order', type: 'number', default: 1 },
        { name: 'featured', label: 'Featured', type: 'checkbox' },
        { name: 'active', label: 'Active', type: 'checkbox', checked: true },
      ],
      columns: ['name', 'display_order', 'featured', 'active'],
    },
    enquiries: {
      label: 'Enquiries',
      endpoint: 'enquiries',
      columns: ['name', 'email', 'project_type', 'status', 'created_at'],
    },
  };

  const content = document.querySelector('#content');
  const navigation = document.querySelector('#navigation');
  const modal = document.querySelector('#modal-backdrop');
  const form = document.querySelector('#record-form');
  const dialogBackdrop = document.querySelector('#dialog-backdrop');
  const dialogTitle = document.querySelector('#dialog-title');
  const dialogContent = document.querySelector('#dialog-content');
  const dialogActions = document.querySelector('#dialog-actions');
  const notice = document.createElement('div');
  notice.className = 'notice';
  content.prepend(notice);
  let selectedModule = 'dashboard';
  let records = [];
  let editing = null;
  let dialogResolver = null;
  let dialogReturnFocus = null;

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (character) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;',
    })[character]);
  }

  async function request(path, options = {}) {
    const response = await fetch(`/api${path}`, {
      credentials: 'same-origin',
      ...options,
      headers: {
        Accept: 'application/json',
        ...(options.body && !(options.body instanceof FormData) ? { 'Content-Type': 'application/json' } : {}),
        ...(options.headers || {}),
      },
    });
    const result = await response.json();
    if (!response.ok || !result.success) {
      const details = Object.values(result.errors || {}).flat().join(' ');
      throw new Error(details || result.message || 'Request failed.');
    }
    return result.data;
  }

  function showNotice(message, type = 'success') {
    notice.textContent = message;
    notice.className = `notice visible ${type}`;
    window.clearTimeout(showNotice.timeout);
    showNotice.timeout = window.setTimeout(() => {
      notice.className = 'notice';
    }, 6000);
  }

  function setLoading(message = 'Loading...') {
    content.querySelector('.table-wrap, .cards, .panel, .empty')?.remove();
    const loading = document.createElement('p');
    loading.className = 'loading';
    loading.textContent = message;
    content.append(loading);
  }

  function renderNavigation() {
    navigation.innerHTML = Object.entries(modules).map(([key, item]) =>
      `<button type="button" data-module="${key}" ${key === selectedModule ? 'aria-current="page"' : ''}>${escapeHtml(item.label)}</button>`
    ).join('');
  }

  function moduleFromHash() {
    const hash = window.location.hash.slice(1);
    return Object.hasOwn(modules, hash) ? hash : 'dashboard';
  }

  function renderDashboard(stats) {
    const cards = [
      { label: 'Videos', value: stats.videos, description: 'Published and draft video content', module: 'videos', icon: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="13" height="14" rx="2"></rect><path d="m16 10 5-3v10l-5-3"></path></svg>' },
      { label: 'Services', value: stats.services, description: 'Creative services in your portfolio', module: 'services', icon: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="7" width="18" height="13" rx="2"></rect><path d="M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2M3 12h18M10 12v2h4v-2"></path></svg>' },
      { label: 'New Enquiries', value: stats.new_enquiries, description: 'Messages waiting for a response', module: 'enquiries', icon: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="m4 7 8 6 8-6"></path></svg>' },
    ];
    content.insertAdjacentHTML('beforeend', `
      <div class="dashboard-heading">
        <div><p class="dashboard-kicker">Portfolio overview</p><h2>Dashboard</h2><p class="subtle">A clear view of your content and incoming enquiries.</p></div>
        <span class="dashboard-date">${escapeHtml(new Intl.DateTimeFormat(undefined, { dateStyle: 'long' }).format(new Date()))}</span>
      </div>
      <div class="dashboard-section-heading"><h3>Content at a glance</h3><span>Live totals from your portfolio</span></div>
      <div class="cards dashboard-cards">${cards.map(({ label, value, description, module, icon }) => `
        <article class="stat">
          <div class="stat-top"><span class="stat-icon">${icon}</span><span class="stat-label">${escapeHtml(label)}</span></div>
          <strong>${Number(value) || 0}</strong>
          <p>${escapeHtml(description)}</p>
          <button class="dashboard-link" type="button" data-dashboard-module="${module}">Manage ${escapeHtml(label)} <span aria-hidden="true">→</span></button>
        </article>
      `).join('')}</div>
      <div class="dashboard-note">
        <div><span class="dashboard-note-mark" aria-hidden="true">F</span><div><strong>Your portfolio workspace</strong><p>Keep videos and services current, and follow up on new enquiries from one place.</p></div></div>
        <button type="button" data-dashboard-module="videos">Open content library <span aria-hidden="true">→</span></button>
      </div>
    `);
    content.querySelectorAll('[data-dashboard-module]').forEach((button) => {
      button.addEventListener('click', () => {
        const moduleName = button.dataset.dashboardModule;
        window.history.replaceState(null, '', `${window.location.pathname}#${moduleName}`);
        loadModule(moduleName);
      });
    });
  }

  function viewField(label, value) {
    return `<div class="record-detail"><dt>${escapeHtml(label)}</dt><dd>${escapeHtml(value || '—')}</dd></div>`;
  }

  function openViewDialog(record) {
    const moduleName = selectedModule;
    let title;
    let media = '';
    let details = '';

    if (moduleName === 'videos') {
      title = record.title || 'Video details';
      const videoUrl = record.video_url || '';
      const thumbnail = record.thumbnail || '';
      const youtubeId = videoUrl.match(/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([A-Za-z0-9_-]+)/)?.[1];
      const vimeoId = videoUrl.match(/vimeo\.com\/(?:video\/)?(\d+)/)?.[1];
      if (youtubeId) {
        media = `<iframe class="record-video-preview" src="https://www.youtube-nocookie.com/embed/${escapeHtml(youtubeId)}" title="${escapeHtml(title)}" allow="encrypted-media; picture-in-picture" allowfullscreen></iframe>`;
      } else if (vimeoId) {
        media = `<iframe class="record-video-preview" src="https://player.vimeo.com/video/${escapeHtml(vimeoId)}" title="${escapeHtml(title)}" allow="fullscreen; picture-in-picture" allowfullscreen></iframe>`;
      } else if (videoUrl) {
        media = `<video class="record-video-preview" src="${escapeHtml(videoUrl)}" ${thumbnail ? `poster="${escapeHtml(thumbnail)}"` : ''} controls playsinline preload="metadata"></video>`;
      } else if (thumbnail) {
        media = `<img class="record-image-preview" src="${escapeHtml(thumbnail)}" alt="${escapeHtml(title)}">`;
      } else {
        media = '<div class="record-media-empty">No video media is available for this record.</div>';
      }
      details = [
        viewField('Title', record.title),
        viewField('Category', record.category),
        viewField('Description', record.description),
        viewField('Display order', String(record.display_order ?? '—')),
        viewField('Featured', Number(record.featured) ? 'Yes' : 'No'),
        viewField('Active', Number(record.active) ? 'Yes' : 'No'),
      ].join('');
    } else if (moduleName === 'services') {
      title = record.name || 'Service details';
      media = record.image_url
        ? `<img class="record-image-preview" src="${escapeHtml(record.image_url)}" alt="${escapeHtml(title)}">`
        : '<div class="record-media-empty">No service image has been uploaded.</div>';
      details = [
        viewField('Service name', record.name),
        viewField('Short description', record.short_description),
        viewField('Full description', record.full_description),
        viewField('Display order', String(record.display_order ?? '—')),
        viewField('Featured', Number(record.featured) ? 'Yes' : 'No'),
        viewField('Active', Number(record.active) ? 'Yes' : 'No'),
      ].join('');
    } else {
      title = record.name || 'Enquiry details';
      details = [
        viewField('Name', record.name),
        viewField('Email', record.email),
        viewField('Phone', record.phone),
        viewField('Company', record.company),
        viewField('Project type', record.project_type),
        viewField('Message', record.message),
        viewField('Status', record.status),
        viewField('Received', record.created_at),
      ].join('');
    }

    showDialog({
      title,
      content: `${media ? `<div class="record-media">${media}</div>` : ''}<dl class="record-details">${details}</dl>`,
      buttons: '<button type="button" class="secondary" data-dialog-close>Close</button>',
    });
  }

  function showDialog({ title, content, buttons, destructive = false }) {
    dialogTitle.textContent = title;
    dialogContent.innerHTML = content;
    dialogActions.innerHTML = buttons;
    dialogBackdrop.classList.add('open');
    dialogBackdrop.dataset.destructive = destructive ? 'true' : 'false';
    dialogReturnFocus = document.activeElement;
    dialogBackdrop.querySelector('[data-dialog-close], [data-dialog-confirm]')?.focus();
  }

  function closeDialog(result = false) {
    dialogBackdrop.classList.remove('open');
    dialogBackdrop.dataset.destructive = 'false';
    if (dialogResolver) {
      const resolve = dialogResolver;
      dialogResolver = null;
      resolve(result);
    }
    if (dialogReturnFocus instanceof HTMLElement && dialogReturnFocus.isConnected) dialogReturnFocus.focus();
    dialogReturnFocus = null;
  }

  function confirmAction({ title, message, confirmLabel = 'Confirm', destructive = false }) {
    showDialog({
      title,
      content: `<p class="confirmation-message">${escapeHtml(message)}</p>`,
      destructive,
      buttons: `<button type="button" class="secondary" data-dialog-close>Cancel</button><button type="button" class="${destructive ? 'danger' : 'primary'}" data-dialog-confirm>${escapeHtml(confirmLabel)}</button>`,
    });
    return new Promise((resolve) => {
      dialogResolver = resolve;
    });
  }

  function displayValue(record, key) {
    if (key === 'featured' || key === 'active') {
      return `<span class="badge ${Number(record[key]) ? '' : 'off'}">${Number(record[key]) ? 'Yes' : 'No'}</span>`;
    }
    if (key === 'created_at') {
      const date = new Date(record[key]);
      return escapeHtml(Number.isNaN(date.getTime()) ? record[key] : date.toLocaleString());
    }
    return escapeHtml(record[key] ?? '');
  }

  function renderTable() {
    const config = modules[selectedModule];
    if (!records.length) {
      content.insertAdjacentHTML('beforeend', `<div class="panel"><div class="empty">No ${escapeHtml(config.label.toLowerCase())} yet. Add the first item to get started.</div></div>`);
      return;
    }
    const columns = config.columns;
    content.insertAdjacentHTML('beforeend', `
      <div class="panel">
        <div class="panel-head">${escapeHtml(config.label)} <span class="subtle">${records.length} record${records.length === 1 ? '' : 's'}</span></div>
        <div class="table-wrap"><table>
          <thead><tr>${columns.map((key) => `<th>${escapeHtml(key.replaceAll('_', ' '))}</th>`).join('')}<th>Actions</th></tr></thead>
          <tbody>${records.map((record, index) => `
            <tr data-index="${index}">
              ${columns.map((key) => `<td>${displayValue(record, key)}</td>`).join('')}
              <td><div class="actions">
                ${selectedModule === 'enquiries'
                  ? `<button type="button" class="small-button" data-action="view">View</button>
                     <select class="small-button" data-action="status" aria-label="Update enquiry status">
                       ${['New', 'Contacted', 'Completed'].map((status) => `<option ${record.status === status ? 'selected' : ''}>${status}</option>`).join('')}
                     </select>`
                  : ['videos', 'services'].includes(selectedModule)
                    ? `<button type="button" class="small-button" data-action="view">View</button>
                       <button type="button" class="small-button" data-action="edit">Edit</button>
                       <button type="button" class="danger small-button" data-action="delete">Delete</button>
                       <button type="button" class="small-button ${Number(record.active) ? 'active-action-disable' : 'active-action-enable'}" data-action="active">${Number(record.active) ? 'Disable' : 'Enable'}</button>
                       <button type="button" class="small-button" data-action="featured">${Number(record.featured) ? 'Unfeature' : 'Feature'}</button>
                       <label class="small-button">Order <input data-action="order" type="number" min="0" value="${Number(record.display_order) || 0}" style="width:54px;border:0"></label>`
                  : `<label class="small-button">Order <input data-action="order" type="number" min="0" value="${Number(record.display_order) || 0}" style="width:54px;border:0"></label>
                     <button type="button" class="small-button" data-action="featured">${Number(record.featured) ? 'Unfeature' : 'Feature'}</button>
                     <button type="button" class="small-button" data-action="active">${Number(record.active) ? 'Disable' : 'Enable'}</button>
                     <button type="button" class="small-button" data-action="edit">Edit</button>`}
                <button type="button" class="danger small-button" data-action="delete">Delete</button>
              </div></td>
            </tr>`).join('')}
          </tbody>
        </table></div>
      </div>
    `);
  }

  function listValue(record, field) {
    if (field === 'project_images') {
      return (record.project_images || []).map((image) => typeof image === 'string' ? image : image.image_url).join('\n');
    }
    if (field === 'services') {
      return record.services || record.work_performed || [];
    }
    return record[field] ?? '';
  }

  function renderField(field, record) {
    const value = record ? listValue(record, field.name) : (field.default ?? (field.checked ? 1 : ''));
    const id = `field-${field.name}`;
    if (field.type === 'file') {
      const currentMedia = record?.[field.currentField] || '';
      const youtubeId = currentMedia.match(/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([A-Za-z0-9_-]+)/)?.[1];
      const vimeoId = currentMedia.match(/vimeo\.com\/(?:video\/)?(\d+)/)?.[1];
      const currentPreview = currentMedia
        ? (selectedModule === 'videos' && youtubeId
          ? `<iframe class="current-video-preview" src="https://www.youtube-nocookie.com/embed/${escapeHtml(youtubeId)}" title="Current video preview" allow="encrypted-media; picture-in-picture" allowfullscreen></iframe>`
          : selectedModule === 'videos' && vimeoId
            ? `<iframe class="current-video-preview" src="https://player.vimeo.com/video/${escapeHtml(vimeoId)}" title="Current video preview" allow="fullscreen; picture-in-picture" allowfullscreen></iframe>`
            : selectedModule === 'videos'
              ? `<video class="current-video-preview" src="${escapeHtml(currentMedia)}" controls playsinline preload="metadata"></video>`
              : `<img class="current-image-preview" src="${escapeHtml(currentMedia)}" alt="Current service image">`)
        : '';
      return `<div class="field full">
        <label for="${id}">${escapeHtml(field.label)}${field.required && !record ? ' *' : ''}</label>
        ${currentPreview}
        <input id="${id}" name="${field.name}" type="file" accept="${escapeHtml(field.accept)}" ${field.required && !record ? 'required' : ''} aria-describedby="${id}-filename">
        <small id="${id}-filename" class="subtle" data-file-name>${record ? (currentMedia ? 'Choose a replacement file to update it.' : 'No file uploaded yet.') : 'No file selected.'}</small>
        <small class="subtle">Maximum file size: ${field.maxBytes / (1024 * 1024)} MB.</small>
      </div>`;
    }
    if (field.type === 'checkbox') {
      return `<div class="field checkbox-field"><label class="check-row" for="${id}"><input id="${id}" name="${field.name}" type="checkbox" ${Number(value) ? 'checked' : ''}><span>${escapeHtml(field.label)}</span></label></div>`;
    }
    if (field.type === 'textarea') {
      const textValue = Array.isArray(value) ? value.join('\n') : value;
      return `<div class="field ${field.full ? 'full' : ''}"><label for="${id}">${escapeHtml(field.label)}${field.required ? ' *' : ''}</label><textarea id="${id}" name="${field.name}" ${field.required ? 'required' : ''}>${escapeHtml(textValue)}</textarea>${field.hint ? `<small class="subtle">${escapeHtml(field.hint)}</small>` : ''}</div>`;
    }
    if (field.type === 'select') {
      const options = [...field.options];
      if (value && !options.includes(value)) options.unshift(value);
      return `<div class="field"><label for="${id}">${escapeHtml(field.label)}${field.required ? ' *' : ''}</label><select id="${id}" name="${field.name}" ${field.required ? 'required' : ''}>${options.map((option) => `<option value="${escapeHtml(option)}" ${value === option ? 'selected' : ''}>${escapeHtml(option)}</option>`).join('')}</select></div>`;
    }
    return `<div class="field${field.name === 'display_order' ? ' order-field' : ''}"><label for="${id}">${escapeHtml(field.label)}${field.required ? ' *' : ''}</label><input id="${id}" name="${field.name}" type="${field.type || 'text'}" value="${escapeHtml(value)}" ${field.required ? 'required' : ''} ${field.type === 'number' ? 'min="0"' : ''}></div>`;
  }

  function openForm(record = null) {
    const config = modules[selectedModule];
    editing = record;
    document.querySelector('#modal-title').textContent = `${record ? 'Edit' : 'Add'} ${config.label.replace(/s$/, '')}`;
    form.innerHTML = `
      <div class="form-grid${['videos', 'services'].includes(selectedModule) ? ' media-upload-form' : ''}">${config.fields.map((field) => renderField(field, record)).join('')}</div>
      <div id="form-error" class="notice error"></div>
      <div class="modal-actions">
        <button type="button" class="secondary" data-close>Cancel</button>
        <button type="submit" class="primary">${record ? 'Save changes' : 'Create item'}</button>
      </div>`;
    modal.classList.add('open');
    form.querySelector('input, textarea, select')?.focus();
  }

  function closeForm() {
    modal.classList.remove('open');
    editing = null;
  }

  async function loadModule(moduleName, preserveNotice = false) {
    selectedModule = moduleName;
    renderNavigation();
    content.querySelectorAll(':scope > :not(.notice)').forEach((element) => element.remove());
    if (!preserveNotice) notice.className = 'notice';
    if (moduleName === 'dashboard') {
      setLoading('Loading dashboard...');
      try {
        const stats = await request('/admin/dashboard');
        content.querySelector('.loading')?.remove();
        renderDashboard(stats);
      } catch (error) {
        showNotice(error.message, 'error');
      }
      return;
    }

    const config = modules[moduleName];
    content.insertAdjacentHTML('beforeend', `
      <div class="page-heading">
        <div><h2>${escapeHtml(config.label)}</h2><p class="subtle">Manage ${escapeHtml(config.label.toLowerCase())} records.</p></div>
        ${moduleName === 'enquiries' ? '' : '<button type="button" class="primary" id="add-record">Add item</button>'}
      </div>
    `);
    content.insertAdjacentHTML('beforeend', '<p class="loading">Loading records...</p>');
    try {
      records = await request(`/admin/${config.endpoint}`);
      content.querySelector('.loading')?.remove();
      renderTable();
    } catch (error) {
      content.querySelector('.loading')?.remove();
      showNotice(error.message, 'error');
    }
  }

  async function refreshSelected() {
    const current = selectedModule;
    await loadModule(current, true);
  }

  function recordPayload(event) {
    const values = new FormData(event.currentTarget);
    const result = {};
    for (const field of modules[selectedModule].fields) {
      if (field.type === 'file') continue;
      if (field.type === 'checkbox') {
        result[field.name] = values.has(field.name) ? 1 : 0;
      } else if (field.type === 'number') {
        result[field.name] = Number(values.get(field.name));
      } else if (field.name === 'technologies' || field.name === 'services' || field.name === 'project_images') {
        result[field.name] = String(values.get(field.name) || '').split(/\r?\n/).map((item) => item.trim()).filter(Boolean);
      } else {
        result[field.name] = String(values.get(field.name) || '').trim();
      }
    }
    return result;
  }

  async function saveRecord(event) {
    event.preventDefault();
    const wasEditing = editing !== null;
    const submit = form.querySelector('[type="submit"]');
    const errorBox = form.querySelector('#form-error');
    const config = modules[selectedModule];
    const payload = recordPayload(event);
    const uploadField = config.fields.find((field) => field.type === 'file');
    const selectedFile = uploadField ? form.elements.namedItem(uploadField.name).files[0] : null;
    if (uploadField && selectedFile) {
      const extension = selectedFile.name.split('.').pop().toLowerCase();
      const acceptedExtensions = uploadField.accept.split(',').map((item) => item.trim().slice(1));
      if (!acceptedExtensions.includes(extension)) {
        errorBox.textContent = 'This file type is not supported.';
        errorBox.className = 'notice visible error';
        return;
      }
      if (selectedFile.size < 1 || selectedFile.size > uploadField.maxBytes) {
        errorBox.textContent = `Choose a file smaller than ${uploadField.maxBytes / (1024 * 1024)} MB.`;
        errorBox.className = 'notice visible error';
        return;
      }
      if (selectedFile.type && selectedFile.type !== 'application/octet-stream') {
        const validMimeTypes = selectedModule === 'videos'
          ? ['video/mp4', 'video/webm', 'video/quicktime']
          : ['image/jpeg', 'image/png', 'image/webp'];
        if (!validMimeTypes.includes(selectedFile.type)) {
          errorBox.textContent = 'The file content does not match an allowed file type.';
          errorBox.className = 'notice visible error';
          return;
        }
      }
    }
    submit.disabled = true;
    submit.textContent = 'Saving...';
    errorBox.className = 'notice';

    try {
      const endpoint = `/admin/${config.endpoint}${editing ? `/${editing.id}` : ''}`;
      const isUploadModule = selectedModule === 'videos' || selectedModule === 'services';
      let body = JSON.stringify(payload);
      let method = editing ? 'PUT' : 'POST';
      if (isUploadModule) {
        const formData = new FormData();
        Object.entries(payload).forEach(([key, value]) => formData.append(key, String(value)));
        if (selectedFile) formData.append(uploadField.name, selectedFile);
        body = formData;
        if (editing) method = 'POST';
      }
      await request(endpoint, {
        method,
        body,
      });
      closeForm();
      showNotice(`${modules[selectedModule].label.replace(/s$/, '')} ${wasEditing ? 'updated' : 'created'} successfully.`);
      await refreshSelected();
    } catch (error) {
      errorBox.textContent = error.message;
      errorBox.className = 'notice visible error';
    } finally {
      if (submit.isConnected) {
        submit.disabled = false;
        submit.textContent = wasEditing ? 'Save changes' : 'Create item';
      }
    }
  }

  async function updateRecord(record, changes) {
    const payload = { ...record, ...changes };
    if (selectedModule === 'videos') {
      delete payload.video_url;
    } else if (selectedModule === 'services') {
      delete payload.image_url;
    }
    if (Array.isArray(payload.project_images)) {
      payload.project_images = payload.project_images.map((item) => typeof item === 'string' ? item : item.image_url);
    }
    if (Array.isArray(payload.services)) {
      payload.services = payload.services;
    }
    await request(`/admin/${modules[selectedModule].endpoint}/${record.id}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
  }

  async function actionForRow(event) {
    const button = event.target.closest('[data-action]');
    const row = event.target.closest('tr[data-index]');
    if (!button || !row) return;
    const record = records[Number(row.dataset.index)];
    const action = button.dataset.action;

    try {
      if (action === 'edit') {
        openForm(record);
      } else if (action === 'view') {
        openViewDialog(record);
      } else if (action === 'delete') {
        const label = record.title || record.name || record.project_name || record.email || 'this record';
        const confirmed = await confirmAction({
          title: 'Delete record?',
          message: `Delete “${label}”? This action cannot be undone.`,
          confirmLabel: 'Delete',
          destructive: true,
        });
        if (!confirmed) return;
        await request(`/admin/${modules[selectedModule].endpoint}/${record.id}`, { method: 'DELETE' });
        showNotice('Record deleted successfully.');
        await refreshSelected();
      } else if (action === 'active') {
        const enable = !Number(record.active);
        const confirmed = await confirmAction({
          title: `${enable ? 'Enable' : 'Disable'} record?`,
          message: enable
            ? 'This record will become visible in the public portfolio.'
            : 'This record will no longer appear in the public portfolio.',
          confirmLabel: enable ? 'Enable' : 'Disable',
          destructive: !enable,
        });
        if (!confirmed) return;
        await updateRecord(record, { active: enable ? 1 : 0 });
        showNotice(`${enable ? 'Enabled' : 'Disabled'} successfully.`);
        await refreshSelected();
      } else if (action === 'featured') {
        await updateRecord(record, { [action]: Number(record[action]) ? 0 : 1 });
        showNotice('Featured state updated.');
        await refreshSelected();
      } else if (action === 'order') {
        const displayOrder = Number(button.querySelector('input').value);
        if (!Number.isInteger(displayOrder) || displayOrder < 0) {
          showNotice('Display order must be a non-negative integer.', 'error');
          return;
        }
        await updateRecord(record, { display_order: displayOrder });
        showNotice('Display order updated.');
        await refreshSelected();
      } else if (action === 'status') {
        const previousStatus = record.status;
        if (button.value === previousStatus) return;
        const confirmed = await confirmAction({
          title: 'Update enquiry status?',
          message: `Change this enquiry from “${previousStatus}” to “${button.value}”?`,
          confirmLabel: 'Update status',
        });
        if (!confirmed) {
          button.value = previousStatus;
          return;
        }
        await request(`/admin/enquiries/${record.id}`, {
          method: 'PUT',
          body: JSON.stringify({ status: button.value }),
        });
        showNotice('Enquiry status updated.');
        await refreshSelected();
      }
    } catch (error) {
      showNotice(error.message, 'error');
    }
  }

  navigation.addEventListener('click', (event) => {
    const button = event.target.closest('[data-module]');
    if (button) {
      const moduleName = button.dataset.module;
      window.history.replaceState(null, '', `${window.location.pathname}#${moduleName}`);
      loadModule(moduleName);
    }
  });

  window.addEventListener('hashchange', () => loadModule(moduleFromHash()));

  content.addEventListener('click', (event) => {
    if (event.target.id === 'add-record') openForm();
    const action = event.target.closest('[data-action]')?.dataset.action;
    if (action && action !== 'order' && action !== 'status') actionForRow(event);
  });

  content.addEventListener('change', (event) => {
    if (event.target.matches('[data-action="status"]')) actionForRow(event);
    if (event.target.matches('[data-action="order"]')) actionForRow(event);
    if (event.target.matches('input[type="file"]')) {
      const filename = event.target.closest('.field')?.querySelector('[data-file-name]');
      if (filename) filename.textContent = event.target.files[0]?.name || 'No file selected.';
    }
  });

  form.addEventListener('submit', saveRecord);
  form.addEventListener('click', (event) => {
    if (event.target.closest('[data-close]')) closeForm();
  });
  modal.addEventListener('click', (event) => {
    if (event.target === modal) closeForm();
  });
  dialogBackdrop.addEventListener('click', (event) => {
    if (event.target === dialogBackdrop || event.target.closest('[data-dialog-close]')) closeDialog(false);
    if (event.target.closest('[data-dialog-confirm]')) closeDialog(true);
  });
  document.addEventListener('keydown', (event) => {
    if (dialogBackdrop.classList.contains('open') && event.key === 'Tab') {
      const focusable = [...dialogBackdrop.querySelectorAll('button:not([disabled]), a[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), video[controls], iframe')]
        .filter((element) => element.getClientRects().length > 0);
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (!first || !last) {
        event.preventDefault();
      } else if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
      return;
    }
    if (event.key === 'Escape') {
      if (dialogBackdrop.classList.contains('open')) closeDialog(false);
      else closeForm();
    }
  });
  document.querySelector('#logout').addEventListener('click', async () => {
    const confirmed = await confirmAction({
      title: 'Log out of FlexaBiz Portfolio CMS?',
      message: 'You will need to sign in again to manage your portfolio.',
      confirmLabel: 'Log out',
      destructive: true,
    });
    if (!confirmed) return;
    try {
      await request('/admin/logout', { method: 'POST' });
      window.location.href = '/admin/login';
    } catch (error) {
      showNotice(error.message, 'error');
    }
  });

  renderNavigation();
  loadModule(moduleFromHash());
})();

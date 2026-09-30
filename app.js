(function () {
  'use strict';

  const STORAGE_KEY = 'todos';

  const form = document.getElementById('todo-form');
  const input = document.getElementById('todo-input');
  const list = document.getElementById('todo-list');
  const emptyState = document.getElementById('empty-state');
  const filtersEl = document.getElementById('filters');
  const itemsLeftEl = document.getElementById('items-left');
  const clearCompletedBtn = document.getElementById('clear-completed');

  let todos = loadTodos();
  let currentFilter = 'all';

  function loadTodos() {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      return raw ? JSON.parse(raw) : [];
    } catch (e) {
      console.warn('Failed to load todos from localStorage:', e);
      return [];
    }
  }

  function saveTodos() {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(todos));
    } catch (e) {
      console.warn('Failed to save todos to localStorage:', e);
    }
  }

  function generateId() {
    return Date.now().toString(36) + Math.random().toString(36).slice(2, 8);
  }

  function formatDate(timestamp) {
    const date = new Date(timestamp);
    return date.toLocaleString();
  }

  function addTodo(text) {
    const trimmed = text.trim();
    if (!trimmed) {
      return;
    }
    todos.push({
      id: generateId(),
      text: trimmed,
      completed: false,
      createdAt: Date.now()
    });
    saveTodos();
    render();
  }

  function removeTodo(id) {
    todos = todos.filter(function (todo) {
      return todo.id !== id;
    });
    saveTodos();
    render();
  }

  function toggleTodo(id) {
    todos = todos.map(function (todo) {
      if (todo.id === id) {
        return Object.assign({}, todo, { completed: !todo.completed });
      }
      return todo;
    });
    saveTodos();
    render();
  }

  function editTodo(id, newText) {
    const trimmed = newText.trim();
    todos = todos.map(function (todo) {
      if (todo.id === id) {
        return Object.assign({}, todo, { text: trimmed || todo.text });
      }
      return todo;
    });
    saveTodos();
    render();
  }

  function clearCompleted() {
    todos = todos.filter(function (todo) {
      return !todo.completed;
    });
    saveTodos();
    render();
  }

  function getFilteredTodos() {
    if (currentFilter === 'active') {
      return todos.filter(function (todo) {
        return !todo.completed;
      });
    }
    if (currentFilter === 'completed') {
      return todos.filter(function (todo) {
        return todo.completed;
      });
    }
    return todos;
  }

  function createTodoElement(todo) {
    const li = document.createElement('li');
    li.className = 'todo-item' + (todo.completed ? ' completed' : '');
    li.dataset.id = todo.id;

    const checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.className = 'todo-checkbox';
    checkbox.checked = todo.completed;
    checkbox.addEventListener('change', function () {
      toggleTodo(todo.id);
    });

    const textWrap = document.createElement('div');
    textWrap.className = 'todo-text-wrap';

    const textSpan = document.createElement('span');
    textSpan.className = 'todo-text';
    textSpan.textContent = todo.text;
    textSpan.addEventListener('dblclick', function () {
      startEditing(li, todo);
    });

    const dateSpan = document.createElement('span');
    dateSpan.className = 'todo-date';
    dateSpan.textContent = formatDate(todo.createdAt);

    textWrap.appendChild(textSpan);
    textWrap.appendChild(dateSpan);

    const deleteBtn = document.createElement('button');
    deleteBtn.type = 'button';
    deleteBtn.className = 'delete-btn';
    deleteBtn.innerHTML = '&times;';
    deleteBtn.setAttribute('aria-label', 'Delete');
    deleteBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      removeTodo(todo.id);
    });

    li.appendChild(checkbox);
    li.appendChild(textWrap);
    li.appendChild(deleteBtn);

    return li;
  }

  function startEditing(li, todo) {
    const textWrap = li.querySelector('.todo-text-wrap');
    textWrap.innerHTML = '';

    const editInput = document.createElement('input');
    editInput.type = 'text';
    editInput.className = 'todo-edit-input';
    editInput.value = todo.text;
    textWrap.appendChild(editInput);
    editInput.focus();
    editInput.select();

    function finishEditing() {
      editTodo(todo.id, editInput.value);
    }

    editInput.addEventListener('blur', finishEditing);
    editInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        editInput.blur();
      } else if (e.key === 'Escape') {
        render();
      }
    });
  }

  function render() {
    const filtered = getFilteredTodos();
    list.innerHTML = '';
    filtered.forEach(function (todo) {
      list.appendChild(createTodoElement(todo));
    });

    emptyState.style.display = todos.length === 0 ? 'block' : 'none';

    const activeCount = todos.filter(function (todo) {
      return !todo.completed;
    }).length;
    itemsLeftEl.textContent = activeCount + ' item' + (activeCount === 1 ? '' : 's') + ' left';

    Array.prototype.forEach.call(filtersEl.querySelectorAll('.filter-btn'), function (btn) {
      btn.classList.toggle('active', btn.dataset.filter === currentFilter);
    });
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    addTodo(input.value);
    input.value = '';
    input.focus();
  });

  filtersEl.addEventListener('click', function (e) {
    const btn = e.target.closest('.filter-btn');
    if (!btn) {
      return;
    }
    currentFilter = btn.dataset.filter;
    render();
  });

  clearCompletedBtn.addEventListener('click', clearCompleted);

  render();
})();

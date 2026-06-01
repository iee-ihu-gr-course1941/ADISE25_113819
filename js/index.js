let pollInterval = null;

function notify(msg, isError = false) {
  const n = $('#notif');
  n.text(msg).toggleClass('error', isError).fadeIn(200);
  setTimeout(() => n.fadeOut(400), 2500);
}

function showLogin() {
  $('#login-section').show();
  $('#lobby').hide();
  if (pollInterval) { clearInterval(pollInterval); pollInterval = null; }
}

function showLobby(username) {
  if (username) $('#logged-username').text('👤 ' + username);
  $('#login-section').hide();
  $('#lobby').show();
  loadGames();
  if (pollInterval) clearInterval(pollInterval);
  pollInterval = setInterval(loadGames, 4000);
}

// Check session status on page load — always returns 200, no 401 in console
$.get('api/whoami.php', function (res) {
  if (res.logged_in) {
    showLobby(res.username);
  }
  // If not logged in, login form is already visible (default CSS)
});

$('#login-btn').on('click', doLogin);
$('#username-input').on('keydown', e => { if (e.key === 'Enter') doLogin(); });

function doLogin() {
  const username = $('#username-input').val().trim();
  if (!username) { $('#login-error').text('Βάλε όνομα χρήστη.'); return; }
  $('#login-error').text('');
  $.ajax({
    url: 'api/login.php',
    method: 'POST',
    contentType: 'application/json',
    data: JSON.stringify({ username }),
    success(res) {
      showLobby(res.username);
    },
    error(xhr) {
      const msg = xhr.responseJSON?.error || 'Σφάλμα σύνδεσης';
      $('#login-error').text(msg);
    }
  });
}

$('#logout-btn').on('click', function () {
  $.post('api/logout.php', () => {
    showLogin();
  });
});

$('#create-game-btn').on('click', function () {
  $(this).prop('disabled', true).text('Δημιουργία...');
  $.ajax({
    url: 'api/create_game.php',
    method: 'POST',
    contentType: 'application/json',
    data: JSON.stringify({}),
    success(res) {
      window.location.href = 'game.html?game_id=' + res.game_id;
    },
    error(xhr) {
      $('#create-game-btn').prop('disabled', false).text('+ Δημιουργία Νέου Παιχνιδιού');
      if (xhr.status === 401) { showLogin(); return; }
      const errMsg = xhr.responseJSON?.error
        || `HTTP ${xhr.status}: ${xhr.statusText} — Έλεγξε το api/debug.php για λεπτομέρειες`;
      notify(errMsg, true);
      console.error('[create_game] status:', xhr.status, '| response:', xhr.responseText);
    }
  });
});

function loadGames() {
  $.get('api/list_games.php', function (games) {
    const $c = $('#games-container');
    if (!games.length) {
      $c.html('<div class="empty-msg">Δεν υπάρχουν παιχνίδια. Δημιούργησε ένα!</div>');
      return;
    }
    let html = '';
    games.forEach(g => {
      const statusLabel = g.status === 'waiting'
        ? '<span class="status-waiting">⏳ Αναμένει αντίπαλο</span>'
        : '<span class="status-playing">🎮 Σε εξέλιξη</span>';
      const btn = g.status === 'waiting'
        ? `<button class="btn-primary" style="width:auto;padding:6px 16px;font-size:0.85rem" onclick="joinGame(${g.id})">Συμμετοχή</button>`
        : (g.is_mine ? `<button class="btn-secondary" style="width:auto;padding:6px 16px;font-size:0.85rem" onclick="watchGame(${g.id})">Συνέχεια</button>` : '');
      html += `<div class="game-item">
        <span><strong>#${g.id}</strong> &nbsp; ${g.creator} &nbsp; ${statusLabel}</span>
        ${btn}
      </div>`;
    });
    $c.html(html);
  }).fail(xhr => {
    if (xhr.status === 401) { showLogin(); }
  });
}

function joinGame(id) {
  $.ajax({
    url: 'api/join_game.php',
    method: 'POST',
    contentType: 'application/json',
    data: JSON.stringify({ game_id: id }),
    success() { window.location.href = 'game.html?game_id=' + id; },
    error(xhr) {
      if (xhr.status === 401) { showLogin(); return; }
      notify(xhr.responseJSON?.error || 'Σφάλμα', true);
    }
  });
}

function watchGame(id) {
  window.location.href = 'game.html?game_id=' + id;
}

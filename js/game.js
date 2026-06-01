const params  = new URLSearchParams(location.search);
const GAME_ID = parseInt(params.get('game_id'));
if (!GAME_ID) { location.href = 'index.html'; }

let state        = null;
let selectedCard = null;
let pollTimer    = null;
let myUserId     = null;

// ── Constants ────────────────────────────────────────────────────────────────
const SUIT_SYMBOL = { S: '♠', H: '♥', D: '♦', C: '♣' };
const SUIT_COLOR  = { S: 'black', H: 'red', D: 'red', C: 'black' };
const VAL_LABEL   = { 1: 'A', 11: 'J', 12: 'Q', 13: 'K' };

// ── Helpers ──────────────────────────────────────────────────────────────────
function cardLabel(c) {
  const v = VAL_LABEL[c.value] || c.value;
  return v + SUIT_SYMBOL[c.suit];
}

function makeCardEl(card, opts = {}) {
  const v     = VAL_LABEL[card.value] || card.value;
  const s     = SUIT_SYMBOL[card.suit];
  const color = SUIT_COLOR[card.suit];
  const $c    = $('<div class="card">').toggleClass('red', color === 'red');
  $c.append($('<div class="top">').text(v + s));
  $c.append($('<div class="suit-center">').text(s));
  $c.append($('<div class="bottom">').text(v + s));
  if (opts.selected) $c.addClass('selected');
  if (opts.onClick)  $c.on('click', opts.onClick);
  $c.data('card', card);
  return $c;
}

function makeBackCard() {
  return $('<div class="card back">');
}

function makeDropZone() {
  return $('<div class="card drop-zone">').html('Ρίψε<br>χαρτί');
}

function collBar(info, xeri, xeriJack) {
  const parts = [
    `<span class="coll-chip">🃏 ${info.count} χαρτιά</span>`,
    `<span class="coll-chip ${info.has_2spades    ? 'yes' : ''}">2♠ ${info.has_2spades    ? '✓' : '✗'}</span>`,
    `<span class="coll-chip ${info.has_10diamonds ? 'yes' : ''}">10♦ ${info.has_10diamonds ? '✓' : '✗'}</span>`,
    `<span class="coll-chip">Φιγ/10: ${info.face10_count}</span>`,
  ];
  if (xeri > 0)     parts.push(`<span class="coll-chip xeri">Ξερή: ${xeri}</span>`);
  if (xeriJack > 0) parts.push(`<span class="coll-chip xeri">Ξερή(J): ${xeriJack}</span>`);
  return parts.join('');
}

function toast(msg, type = '') {
  const $t = $('#toast').removeClass('error-toast xeri-toast').text(msg);
  if (type === 'error') $t.addClass('error-toast');
  if (type === 'xeri')  $t.addClass('xeri-toast');
  $t.stop(true).fadeIn(200);
  setTimeout(() => $t.fadeOut(400), 3000);
}

// ── Render ────────────────────────────────────────────────────────────────────
function render(s) {
  const isMyTurn = s.is_my_turn && s.status === 'playing';

  $('#header-info').text(`Παιχνίδι #${s.game_id} | Γύρος ${s.round}`);
  $('#round-label').text(`Γύρος ${s.round}`);

  if (s.opponent) {
    $('#opp-name').text(s.opponent.username + (isMyTurn ? '' : ' 🎯'));
    const $oh = $('#opp-hand-display').empty();
    for (let i = 0; i < s.opponent.hand_count; i++) $oh.append(makeBackCard());
    if (s.opponent.hand_count === 0) $oh.append($('<span class="hand-empty">').text('Άδειο χέρι'));
    $('#opp-coll-bar').html(collBar(s.opponent.collected, s.opponent.xeri_count, s.opponent.xeri_jack_count));
  } else {
    $('#opp-name').text('Αναμονή αντιπάλου...');
    $('#opp-hand-display').html('<span class="hand-empty">—</span>');
  }

  myUserId = s.me.id;
  $('#my-name').text(s.me.username + (isMyTurn ? ' 🎯' : ''));
  $('#my-coll-bar').html(collBar(s.me.collected, s.me.xeri_count, s.me.xeri_jack_count));

  renderTable(s);
  renderHand(s.me.hand, isMyTurn);

  $('#deck-info').text(`Τράπουλα: ${s.deck_count} χαρτιά απομένουν`);

  const $msg = $('#msg-bar').removeClass('my-turn opp-turn waiting finished');
  if (s.status === 'waiting') {
    $msg.addClass('waiting').html(`⏳ Αναμονή αντιπάλου… <br><small>Μοιράσου το <strong>ID: ${s.game_id}</strong></small>`);
  } else if (s.status === 'finished') {
    $msg.addClass('finished').text('🏁 Το παιχνίδι τελείωσε!');
    renderScores(s.scores);
  } else if (isMyTurn) {
    const hint = selectedCard
      ? `Επιλεγμένο: ${cardLabel(selectedCard)} — Κλίκ στο τραπέζι για ρίψη ή στο χαρτί για μάζεμα`
      : 'Η σειρά σου! Επίλεξε χαρτί από το χέρι σου.';
    $msg.addClass('my-turn').text('▶ ' + hint);
  } else {
    $msg.addClass('opp-turn').text('⏳ Περιμένεις τον αντίπαλο...');
  }
}

function renderTable(s) {
  const $ta      = $('#table-area').empty();
  const isMyTurn = s.is_my_turn && s.status === 'playing';
  const $stack   = $('<div class="table-stack">');

  if (s.table_count === 0) {
    if (isMyTurn) {
      $stack.append(makeDropZone().on('click', () => playCard('throw')));
    } else {
      $stack.append(
        $('<div>').css({ color: '#888', fontStyle: 'italic', fontSize: '0.9rem', padding: '20px' }).text('Άδειο τραπέζι')
      );
    }
    $stack.append($('<div class="stack-label">').text('Τραπέζι (άδειο)'));
  } else {
    const $top = makeCardEl(s.table_top, {
      onClick: isMyTurn && selectedCard ? () => playCard('pickup') : null
    });
    if (isMyTurn && selectedCard) $top.attr('title', 'Κλίκ για μάζεμα').css('cursor', 'pointer');
    else $top.css('cursor', 'default');

    $stack.append($top);
    $stack.append($('<div class="stack-label">').text(`${s.table_count} χαρτί${s.table_count === 1 ? '' : 'ά'} στη στοίβα`));

    if (isMyTurn) {
      const $dzStack = $('<div class="table-stack">');
      $dzStack.append(makeDropZone().on('click', () => playCard('throw')));
      $dzStack.append($('<div class="stack-label">').text('Ρίψη'));
      $ta.append($dzStack);
    }
  }

  $ta.append($stack);
}

function renderHand(hand, isMyTurn) {
  const $hc = $('#hand-cards').empty();
  if (!hand.length) {
    $hc.append($('<div class="hand-empty">').text('Άδειο χέρι'));
    $('#action-hint').text('');
    return;
  }
  hand.forEach(card => {
    const isSel = selectedCard && card.suit === selectedCard.suit && card.value === selectedCard.value;
    const $c    = makeCardEl(card, {
      selected: isSel,
      onClick: isMyTurn ? () => selectCard(card) : null
    });
    if (!isMyTurn) $c.css('cursor', 'default');
    $hc.append($c);
  });

  if (isMyTurn && !selectedCard)    $('#action-hint').text('Κλίκ σε χαρτί για να το επιλέξεις');
  else if (isMyTurn && selectedCard) $('#action-hint').text('Κλίκ στη ζώνη ρίψης ή στο χαρτί του τραπεζιού');
  else                               $('#action-hint').text('');
}

function renderScores(scores) {
  if (!scores) return;
  $('#scores-panel').show();
  const maxScore = Math.max(...scores.map(s => s.score));
  let html = '';
  scores.forEach(s => {
    const isWinner = s.score === maxScore;
    html += `<div class="score-row ${isWinner ? 'winner' : ''}">
      <span>${isWinner ? '🏆 ' : ''}${s.username}</span>
      <span class="pts">${s.score} πόντοι</span>
    </div>`;
  });
  $('#scores-content').html(html);
}

// ── Actions ───────────────────────────────────────────────────────────────────
function selectCard(card) {
  if (selectedCard && selectedCard.suit === card.suit && selectedCard.value === card.value) {
    selectedCard = null;
  } else {
    selectedCard = card;
  }
  render(state);
}

function playCard(action) {
  if (!selectedCard)      { toast('Επίλεξε πρώτα χαρτί από το χέρι σου', 'error'); return; }
  if (!state.is_my_turn)  { toast('Δεν είναι η σειρά σου', 'error'); return; }

  $('#hand-cards .card').off('click').css('cursor', 'wait');
  $('#table-area .card').off('click').css('cursor', 'wait');

  $.ajax({
    url: 'api/play_card.php',
    method: 'POST',
    contentType: 'application/json',
    data: JSON.stringify({ game_id: GAME_ID, action, card: selectedCard }),
    success(res) {
      selectedCard = null;
      if (res.xeri_jack)    toast('🔥 ΞΕΡΗ ΜΕ ΒΑΛΕ! +20 πόντοι', 'xeri');
      else if (res.xeri)    toast('⚡ ΞΕΡΗ! +10 πόντοι', 'xeri');
      fetchState();
    },
    error(xhr) {
      toast(xhr.responseJSON?.error || 'Σφάλμα', 'error');
      fetchState();
    }
  });
}

// ── Poll ──────────────────────────────────────────────────────────────────────
function fetchState() {
  $.get('api/get_state.php?game_id=' + GAME_ID, function (s) {
    state = s;
    render(s);
    clearTimeout(pollTimer);
    if (s.status === 'finished') return;
    pollTimer = setTimeout(fetchState, s.is_my_turn ? 3000 : 2000);
  }).fail(function (xhr) {
    if (xhr.status === 401)       window.location.href = 'index.html';
    else if (xhr.status === 403)  toast('Δεν είσαι μέλος αυτού του παιχνιδιού', 'error');
  });
}

// ── Init ──────────────────────────────────────────────────────────────────────
fetchState();

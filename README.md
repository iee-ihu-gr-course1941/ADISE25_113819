# Ξερή - Web Card Game

Υλοποίηση του παιχνιδιού Ξερή ως Web Application (PHP/MySQL/jQuery).

## Εγκατάσταση (XAMPP)

1. Αντιγράψτε τον φάκελο `PlayingCards` στο `C:\xampp\htdocs\`
2. Ανοίξτε το **XAMPP Control Panel** και εκκινήστε **Apache** και **MySQL**
3. Ανοίξτε browser → `http://localhost/phpmyadmin`
4. Εκτελέστε το αρχείο `xeri.sql` (Import ή copy-paste στο SQL tab)
5. Ανοίξτε `http://localhost/PlayingCards/`

## Παιχνίδι

1. Δύο παίκτες ανοίγουν το `http://localhost/PlayingCards/` σε **διαφορετικούς** browsers (ή browser tabs)
2. Κάθε παίκτης κάνει login με username
3. Ένας δημιουργεί παιχνίδι → ο άλλος κάνει "Συμμετοχή"

## API Endpoints

| Method | URL | Περιγραφή |
|--------|-----|-----------|
| POST | `/api/login.php` | `{"username":"name"}` → login/register |
| POST | `/api/logout.php` | Αποσύνδεση |
| POST | `/api/create_game.php` | Δημιουργία παιχνιδιού |
| POST | `/api/join_game.php` | `{"game_id":1}` → Ένταξη |
| GET  | `/api/get_state.php?game_id=1` | Κατάσταση παιχνιδιού |
| POST | `/api/play_card.php` | `{"game_id":1,"action":"throw","card":{"suit":"S","value":7}}` |
| GET  | `/api/list_games.php` | Λίστα παιχνιδιών |

### Παράδειγμα χρήσης με curl

```bash
# Login
curl -X POST http://localhost/PlayingCards/api/login.php \
  -H "Content-Type: application/json" \
  -d '{"username":"player1"}' -c cookies.txt

# Δημιουργία παιχνιδιού
curl -X POST http://localhost/PlayingCards/api/create_game.php \
  -H "Content-Type: application/json" -d '{}' -b cookies.txt

# Κατάσταση
curl http://localhost/PlayingCards/api/get_state.php?game_id=1 -b cookies.txt

# Ρίψη χαρτιού
curl -X POST http://localhost/PlayingCards/api/play_card.php \
  -H "Content-Type: application/json" \
  -d '{"game_id":1,"action":"throw","card":{"suit":"S","value":7}}' -b cookies.txt

# Μάζεμα χαρτιών
curl -X POST http://localhost/PlayingCards/api/play_card.php \
  -H "Content-Type: application/json" \
  -d '{"game_id":1,"action":"pickup","card":{"suit":"H","value":7}}' -b cookies.txt
```

### Suits / Χρώματα
- `S` = Σπαθί ♠
- `H` = Κούπα ♥  
- `D` = Καρό ♦
- `C` = Σταυρός ♣

### Values / Τιμές
- `1` = Άσσος (A)
- `2–10` = Αριθμοί
- `11` = Βαλές (J)
- `12` = Ντάμα (Q)
- `13` = Ρήγας (K)

## Κανόνες

- Κάθε γύρος: 6 χαρτιά ανά παίκτη, 4 στοιβαγμένα στο τραπέζι
- Κίνηση: ρίψη χαρτιού **ή** μάζεμα (ίδια φιγούρα/ίδιος αριθμός ή με Βαλέ)
- **Ξερή**: μάζεμα όταν υπάρχει μόνο 1 χαρτί στο τραπέζι (+10 πόντοι, με Βαλέ +20)
- Τέλος γύρου: μοίρασμα 6 χαρτιών ανά παίκτη (χωρίς νέα χαρτιά στο τραπέζι)
- Τέλος παιχνιδιού: τράπουλα τελείωσε + άδεια χέρια

### Βαθμολογία
- 3 πόντοι: παίκτης με τα περισσότερα χαρτιά (αν ισοβαθμία → κανείς)
- 1 πόντος: 2 Σπαθί
- 1 πόντος: 10 Καρό
- 1 πόντος: κάθε Ρήγας, Ντάμα, Βαλές, ή 10 (εκτός 10 Καρό)
- 10 πόντοι: κάθε Ξερή
- 20 πόντοι: κάθε Ξερή με Βαλέ
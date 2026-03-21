# Side Quest API Documentation

## Base URL
- Local: `http://localhost:8000`
- Production: `https://yourdomain.com`

## Authentication

### Session-Based
- Uses PHP sessions with CSRF tokens on forms
- Secure HTTP-only cookies
- Auto-logout after 24 hours

### Required Headers for API Calls
```
Content-Type: application/json
Accept: application/json
```

---

## User Endpoints

### 1. Register User
**POST** `/register.php`

**Parameters:**
```json
{
  "csrf_token": "token_value",
  "username": "string (3-20 chars, alphanumeric_)",
  "email": "valid@email.com",
  "password": "string (min 8 chars)",
  "password_confirm": "string (must match)"
}
```

**Response:**
```json
{
  "success": true,
  "user_id": 123,
  "message": "Account created successfully"
}
```

**Errors:**
- 400: Invalid input format
- 409: Username or email already exists
- 422: Passwords don't match

---

### 2. Login User
**POST** `/login.php`

**Parameters:**
```json
{
  "csrf_token": "token_value",
  "username": "username_or_email",
  "password": "password"
}
```

**Response:**
```json
{
  "success": true,
  "user_id": 123,
  "message": "Logged in successfully"
}
```

**Errors:**
- 401: Invalid credentials
- 400: Missing parameters

---

### 3. Get User Dashboard
**GET** `/dashboard.php`

**Requires:** User session

**Returns:** HTML dashboard with:
- Current quest
- User stats (level, XP, completed, streak)
- Leaderboard
- Submission history

---

### 4. Logout
**GET** `/logout.php`

**Returns:** Redirect to login page

---

## Quest Endpoints

### 1. Get/Assign Quest
**GET** `/get_quest.php`

**Requires:** User session

**Response:**
```json
{
  "success": true,
  "current": false,
  "quest": {
    "id": 42,
    "quest_id": 5678,
    "title": "Compliment 3 random people",
    "description": "Walk around and give genuine compliments",
    "difficulty": "easy",
    "type": "dare",
    "xp_reward": 10,
    "status": "assigned"
  }
}
```

**Logic:**
1. Checks for current active quest
2. Returns it if exists
3. Otherwise finds random unfinished quest
4. Adjusts difficulty based on user level
5. Prevents repeats of completed quests

---

## Submission Endpoints

### 1. Submit Proof
**POST** `/submit_proof.php`

**Requires:** User session

**Form Data:**
```
user_quest_id: 42
proof: <image_file> (multipart/form-data)
```

**Accepted Formats:**
- JPEG, JPG
- PNG
- GIF
- WebP

**Size Limits:**
- Min: 200x200px
- Max: 5MB

**Response:**
```json
{
  "success": true,
  "submission_id": 789,
  "message": "Proof submitted successfully"
}
```

**Errors:**
- 400: No file or invalid format
- 413: File too large
- 415: Invalid MIME type
- 422: Image too small

**Auto-Verification:**
- Image must exist and be valid
- Confidence score calculated
- If confidence > 0.7: auto-approved
- Otherwise: marked pending for admin review

---

### 2. Reject Submission
**POST** `/reject.php`

**Requires:** Admin session

**Parameters:**
```json
{
  "submission_id": 789,
  "notes": "Image quality too low"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Submission rejected"
}
```

**Logic:**
- Max 3 retry attempts
- After 3 attempts: quest expires
- User can get new quest

---

## Admin Endpoints

### 1. Admin Login
**GET/POST** `/x9_admin_portal_hidden/admin-login.php?token=x9_admin_portal_hidden`

**POST Parameters:**
```json
{
  "csrf_token": "token_value",
  "username": "admin_username",
  "password": "admin_password"
}
```

**Response:** Redirect to admin panel

---

### 2. Admin Dashboard
**GET** `/x9_admin_portal_hidden/admin.php?token=...&section=dashboard`

**Requires:** Admin session

**Returns:**
- User statistics
- Quest counts
- Pending submissions
- Recent activity

---

### 3. Quest Management
**GET/POST** `/x9_admin_portal_hidden/admin.php?token=...&section=quests`

**POST (Add Quest):**
```json
{
  "quest_action": "add",
  "csrf_token": "token_value",
  "title": "string",
  "description": "text",
  "difficulty": "easy|medium|hard|insane",
  "type": "truth|dare|social|dark_humor|challenge|physical",
  "xp_reward": 10,
  "keywords": "comma,separated,keywords"
}
```

---

### 4. Submissions Review
**GET** `/x9_admin_portal_hidden/admin.php?token=...&section=submissions`

**Features:**
- View pending submissions
- Review uploaded proofs
- Approve with auto-XP award
- Reject with notes
- View submission history

---

## Data Models

### User Object
```json
{
  "id": 123,
  "username": "player1",
  "email": "player@example.com",
  "display_name": "Player One",
  "level": 5,
  "xp": 450,
  "total_completed": 42,
  "current_streak": 7,
  "status": "active"
}
```

### Quest Object
```json
{
  "id": 5678,
  "title": "Challenge Title",
  "description": "Full quest description",
  "difficulty": "medium",
  "type": "dare",
  "xp_reward": 25,
  "difficulty_multiplier": 1.1,
  "keywords": "proof,screenshot",
  "is_active": true
}
```

### Submission Object
```json
{
  "id": 789,
  "user_id": 123,
  "user_quest_id": 42,
  "quest_id": 5678,
  "file_path": "/uploads/proofs/...",
  "file_name": "proof_123_5678_123456.jpg",
  "file_size": 125000,
  "verification_status": "pending|approved|rejected",
  "confidence_score": 0.85,
  "submitted_at": "2026-03-21T10:30:00Z",
  "verified_at": "2026-03-21T11:00:00Z"
}
```

---

## Error Handling

### Standard Error Response
```json
{
  "success": false,
  "error": "Error message describing what went wrong"
}
```

### HTTP Status Codes
- 200: Success
- 400: Bad Request (invalid data)
- 401: Unauthorized (not logged in)
- 403: Forbidden (not authorized)
- 404: Not Found
- 409: Conflict (duplicate)
- 413: Payload Too Large
- 415: Unsupported Media Type
- 422: Unprocessable Entity
- 500: Server Error

---

## Rate Limiting

Currently not implemented but recommended additions:
- 5 failed login attempts → 15 min lockout
- Quest submission: max 3 per day per user
- API calls: max 100/minute per IP

---

## Security Notes

1. **CSRF Protection**
   - All POST forms require CSRF token
   - Token regenerated per session

2. **Input Validation**
   - All user input sanitized
   - File uploads validated by type/size/dimensions
   - HTML escaped in output

3. **Authentication**
   - Passwords hashed with bcrypt (cost 10)
   - Sessions use secure HTTP-only cookies
   - Admin URLs require secret token

4. **Database**
   - Prepared statements prevent SQL injection
   - User queries isolated by user_id
   - Admin actions logged to audit table

---

## Rate Limiting Best Practices

```php
// Add to config.php
function rate_limit($key, $max = 5, $window = 60) {
    $cache_key = 'rate_limit:' . $key;
    $count = $_SESSION[$cache_key] ?? 0;
    
    if ($count >= $max) {
        http_response_code(429);
        die(json_encode(['error' => 'Too many requests']));
    }
    
    $_SESSION[$cache_key] = $count + 1;
}
```

---

## Webhook Events (Future)

Recommended events for third-party integration:
- `user.registered`
- `user.leveled_up`
- `quest.completed`
- `submission.approved`
- `user.streak_lost`

---

## Integration Examples

### JavaScript Fetch
```javascript
// Get quest
const response = await fetch('/get_quest.php');
const data = await response.json();

if (data.success) {
  console.log('Quest:', data.quest);
}

// Submit proof
const formData = new FormData();
formData.append('user_quest_id', questId);
formData.append('proof', fileInput.files[0]);

const result = await fetch('/submit_proof.php', {
  method: 'POST',
  body: formData
});
```

### cURL
```bash
# Get quest
curl -b cookies.txt https://domain.com/get_quest.php

# Submit proof
curl -b cookies.txt -F "proof=@image.jpg" \
  -F "user_quest_id=42" \
  https://domain.com/submit_proof.php
```

---

**API Version**: 1.0.0
**Last Updated**: March 2026
**Status**: Stable

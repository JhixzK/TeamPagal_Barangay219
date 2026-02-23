# User Guide: Forgot Password - SMS Method

## Quick Start

### For SMS Users (Recommended)

#### Step 1: Select SMS Method
1. Go to login page → Click **"Forgot Password?"**
2. Click the **📱 SMS** button
3. You should see the mobile number input field

#### Step 2: Enter Mobile Number
- **Format Accepted:**
  - `09171234567` (Local format)
  - `+639171234567` (International format)
  - `+63-917-123-4567` (With dashes)
  - `917 123 4567` (With spaces)

- **System Will Auto-Convert:**
  - `09171234567` → becomes `+639171234567`
  - You'll see it displayed as: `+63-9171234567`

- **Real-Time Feedback:**
  - ⭕ Incomplete: Shows "X/9 digits" in red
  - ✅ Complete: Shows "Valid: +63-9171234567 ✓" in green

#### Step 3: Submit & Wait
- Click **"Send Reset Code"** button
- Spinner shows "Sending code..."
- After validation, you'll see: ✅ **Success!**

#### Step 4: Check Your Phone
- **You will receive:**
  - SMS with 5-digit code
  - Example: `45892`
  - **Code expires in 5 minutes**
  - **You have 5 attempts**

#### Step 5: Enter OTP Code
1. Return to browser
2. Enter each digit (1-2-3-4-5)
3. Fields auto-advance to next digit
4. Or paste entire code at once

**Attempt Counter:**
- 🟢 Green: 3+ attempts remaining → "5 attempts remaining"
- 🟠 Orange: 2 attempts remaining → "2 attempts remaining"
- 🔴 Red: Final attempt → "1 attempt remaining"

#### Step 6: Create New Password

**Your password must have:**
- ✓ At least 8 characters
- ✓ 1 UPPERCASE letter (A-Z)
- ✓ 1 lowercase letter (a-z)
- ✓ 1 Number (0-9)
- ✓ 1 Special character (!@#$%^&*)

**Example Strong Password:**
- ✅ `MyPassword123!`
- ✅ `Barangay2024@Secure`
- ❌ `password` (no uppercase, no number, no special)
- ❌ `Pass123` (no special character)

1. Type new password
2. Confirm password (type again)
3. See strength indicator fill green
4. Click **"Reset Password"** button

#### Step 7: Success! Return to Login
- Message: "Password successfully changed!"
- Auto-redirects to login after 2.5 seconds
- Login with your new password

---

## Troubleshooting

### "Mobile number not found in resident database"
**What this means:** Your phone number is not registered with the barangay

**What to do:**
1. Verify you entered the correct number
2. Contact barangay office to register/update your mobile number
3. Try email method instead

### "Your resident record is not approved yet"
**What this means:** You registered but haven't been approved as an active resident

**What to do:**
1. Wait for barangay staff to approve your registration
2. Contact barangay office to check status
3. Come back after approval

### "Invalid Philippine mobile number"
**What this means:** Format is incorrect

**Correct formats:**
- ✅ `09171234567`
- ✅ `+639171234567`
- ✅ `+63-917-123-4567`

**Wrong formats:**
- ❌ `63917123456` (missing 0 or +)
- ❌ `9171234567` (missing 0 or +63)
- ❌ `09-171-234-567` (too many dashes)

### Code not received on phone
**Try:**
1. Wait 30 seconds (SMS can take time)
2. Check spam/junk folder
3. Click "Resend Code" button
   - Wait 60 seconds between requests
   - Maximum 5 resends per hour

### "Maximum OTP attempts exceeded"
**What happened:** You tried 5 incorrect codes

**What to do:**
1. Click "Forgot Password?" again
2. Start over with a new code
3. Make sure you enter the exact code from SMS

### "OTP expired"
**What happened:** 5 minutes have passed, code no longer valid

**What to do:**
1. Click "Resend Code"
2. You'll get a new OTP code
3. Enter the new code (5 attempts again)

### Password doesn't meet requirements
**Error:** "Password must be at least 8 characters"

**Check your password has:**
```
✓ 8+ characters     (at least 8)
✓ UPPERCASE letter  (A, B, C, etc.)
✓ lowercase letter  (a, b, c, etc.)
✓ Number            (0, 1, 2, etc.)
✓ Special character (!@#$%^&*)
```

**Example of GOOD password:**
- `Welcome@Barangay2024` ✅
- `MyNewPass123#` ✅
- `Secure$Password88` ✅

**Example of BAD password:**
- `password123` ❌ (no UPPERCASE, no special char)
- `Password!` ❌ (no number)
- `Abc123` ❌ (too short, no special char)

---

## Security Tips

✓ **Never share your OTP code with anyone**
- Barangay staff will NEVER ask for your OTP
- Only you receive it on your phone

✓ **Only use official barangay website**
- Check URL: `http://localhost/TeamPagal_Barangay219/...`
- Bookmark the official page

✓ **Enter OTP within 5 minutes**
- Code expires after 5 minutes
- You have 5 attempts to get it right

✓ **Use strong password**
- Don't reuse old password
- Don't use easy words (password, 123456, etc.)
- Make it 8+ characters with mix of types

✓ **Log out when done**
- Sign out after password reset
- Log in with new password to confirm

---

## Email Method (Alternative)

If you prefer email instead of SMS:

1. Click **"📧 Email"** button instead
2. Enter registered email address
3. Click **"Send Reset Code"**
4. Check your email for reset link
   - Message expires in 60 minutes
   - Open link and proceed to password reset
5. Follow password creation steps (same as SMS flow)

---

## Comparison: SMS vs Email

| Feature | SMS | Email |
|---------|-----|-------|
| **Speed** | Instant (few seconds) | May take 1-2 minutes |
| **Expiry** | 5 minutes | 60 minutes |
| **Attempts** | 5 attempts | N/A |
| **Privacy** | Only you get SMS | Check email carefully |
| **Convenience** | Phone always with you | Need access to email |

---

## What Happens After Successful Reset

1. ✅ Password changed successfully
2. ⏱️ Auto-redirect to login (2.5 seconds)
3. 🔐 Log in with your NEW password
4. ✨ Access your account as normal

**Your old password no longer works**
- Make sure you remember the new password
- Consider writing it down securely

---

## Still Need Help?

**Contact Information:**
- 📍 Visit Barangay Office
- 📞 Call Barangay Office
- 📧 Email: barangay@email.com
- 🕐 Office Hours: Mon-Fri, 8am-5pm

**Tell them:**
1. You used the "Forgot Password" feature
2. Whether you used SMS or Email
3. Any error message you received
4. What step you're stuck on

**Staff will help:**
✓ Verify your registration status
✓ Confirm mobile number in database
✓ Update contact information if needed
✓ Assist with account recovery

---

## Quick Checklist

### Before You Start
- [ ] Do you have your mobile phone with you?
- [ ] Is your mobile number registered with barangay?
- [ ] Do you have 5 minutes available?

### During Reset
- [ ] Selected SMS method ✓
- [ ] Entered valid mobile number ✓
- [ ] Received OTP code on phone ✓
- [ ] Entered OTP correctly ✓
- [ ] Created password with all requirements ✓
- [ ] Confirmed password matches ✓

### After Reset
- [ ] Successfully logged in with new password ✓
- [ ] Can access your account ✓
- [ ] Remember your new password ✓

---

## Frequently Asked Questions

**Q: Can I use my old phone number?**
A: Only if it's registered with the barangay. Update your profile with current number.

**Q: What if I want to change the phone number in my account?**
A: Contact barangay office to update registered mobile number first.

**Q: Can someone else use my OTP?**
A: No, each person has their own unique OTP sent to their phone.

**Q: What if I forget my password again?**
A: You can use this same Forgot Password process again.

**Q: Is my new password secure?**
A: Yes, as long as it has 8+ chars, uppercase, lowercase, number, and special char.

**Q: How long does the OTP stay active?**
A: Only 5 minutes. After that, you need to request a new code.

**Q: Can I resend the code unlimited times?**
A: No, maximum 5 resends per hour with 60-second wait between each.

**Q: What if I make a typo in my new password?**
A: You'll need to request another password reset (confirm field will show mismatch).

**Q: Will I receive a confirmation email?**
A: Depending on system configuration, you may receive a confirmation email.

---

## Version Information

- **System:** E-Barangay Information Management System
- **Feature:** Forgot Password with SMS & Email Support
- **Date Updated:** 2024
- **Supported Devices:** Mobile phones, tablets, computers
- **Browser Support:** Chrome, Firefox, Safari, Edge

---

**Thank you for using our system! We're here to help.**

For any questions, please visit your barangay office or contact them during business hours.

**Stay secure. Stay connected. 🛡️**

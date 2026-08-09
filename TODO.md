# MedFind Real-Time WebSocket Implementation

## Objective
Implement the "Real-Time" requirement of the capstone: live stock sync + live chat notifications via Laravel Reverb WebSocket.

## Steps
- [x] Present plan and get user approval
- [ ] Create `app/Events/InventoryUpdated.php` (broadcast event)
- [ ] Create `app/Events/MessageSent.php` (broadcast event)
- [ ] Fire `InventoryUpdated` in `InventoryController` (store/update/destroy)
- [ ] Fire `InventoryUpdated` in `ReceivingController`
- [ ] Fire `MessageSent` in `MessageController` (store/reply)
- [ ] Add Echo listeners in `public/js/medfind.js` (live map update, live unread count, live chat)
- [ ] Add frontend real-time listeners for pharmacy dashboard
- [ ] Add Reverb env keys
- [ ] Verify PHP syntax (`php -l`)
- [ ] Document how to run Reverb server

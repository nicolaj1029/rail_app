# Mobile Device QA Checklist

This is the execution checklist for the first real device pass. Run it on a physical Android phone before calling the mobile app finished.

## Environment

- Install the app on a physical device
- Use a reachable backend URL, not `localhost`
- Verify login/session/device registration works on the device network

## 1. Shell and navigation

- Open app
- Confirm `Home`, `Trips`, `Claims`, `Chat`, `Profile` all load
- Confirm no blocking crash on app start
- Confirm refresh works from app bar

## 2. Device diagnostics

- Open `Profile` -> `Device QA`
- Confirm device ID is present or can be registered
- Confirm API base URL is correct for the device
- Check location service state
- Check location permission state
- Run notification permission/test

## 3. Upload

- Test upload from camera
- Test upload from gallery
- Test upload from:
  - `Live Assist`
  - `Chat`
  - `Case Close`
  - `Manual Journey`
- Confirm success/failure feedback is visible

## 4. Chat

- Open generic chat
- Open context chat from:
  - `Home`
  - `Trips`
  - `Claims`
- Send quick reply
- Send free text
- Upload document in chat
- Confirm chat scrolls and keeps session state

## 5. Journeys and claims

- Open `Trips`
- Confirm `Needs review`, `Active now`, `Other journeys`
- Open `Review`
- Open `Chat`
- Open `Claims`
- Confirm submitted cases render correctly

## 6. Pendler mode

- Enable commuter profile in `Profile`
- Set operator, country, product, route
- Return to `Home`
- Confirm pendler home variant appears
- Confirm chat/review actions prefer commuter flow

## 7. Live assist and tracking

- Open `Live Assist`
- Start tracking
- Confirm no immediate permission/runtime crash
- Confirm device shows correct tracking state
- If moving test is possible:
  - verify pings are sent
  - verify journeys appear later in `Trips`

## 8. Notifications

- Request notification permission
- Trigger local notification test
- Confirm notification is shown on device

## 9. Background / lifecycle

- Put app in background
- Resume app
- Confirm session and current screen survive
- Confirm no duplicate bootstrapping or broken state

## 10. Release blockers

Do not call the mobile app finished if any of these fail:

- camera/gallery upload broken
- chat cannot send or loses session
- journeys/claims do not load on device
- notification permission/test broken
- location permission or tracking crashes app
- commuter profile is not persisted

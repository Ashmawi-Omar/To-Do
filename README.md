# To-Do

A classic To-Do app where you can write down, complete, edit and remove the things you want to accomplish.

## Stack

This app is built with plain **HTML, CSS and JavaScript**, with to-do's persisted in the browser's
`localStorage`. No PHP/Laravel or database is required: a static front-end covers every user story
(add, complete, remove, edit, filter by active/completed, show creation date, and persist across
browser sessions) with the smallest possible footprint. Docker is still used to serve the app
through nginx, so you get a reproducible, one-command way to run it without installing anything
locally — if the project grows a real backend (auth, multi-device sync, etc.) later on, Laravel
would be a solid choice to layer in at that point.

## Features

- Add a to-do by typing in the input field and pressing Enter or the Add button
- Mark a to-do as completed via its checkbox
- Remove a to-do with the delete (×) button
- Edit a to-do by double-clicking its text
- Filter the list by All / Active / Completed
- See the creation date/time of each to-do
- To-do's are stored in `localStorage`, so they persist when you close and reopen the browser

## Running with Docker

```sh
docker compose up --build
```

Then open http://localhost:8080 in your browser.

## Running without Docker

Simply open `index.html` in your browser, or serve the folder with any static file server.

# task-tree
This will be my "first" personal project produced for Boot.dev. I'd prefer to keep this closed-source, so this repo will be moved to my private GitLab as soon as I'm able, but the course expects that I produce open source code. I assume I'll be graded on it at some point, and I can only hope it doesn't get crawled in the mean time. Regardless: the intent is to first produce a refactor of my old xLib project, ~~adding 2FA and some other features,~~ cleaning it up, and to produce from that a tool that tracks tasks like a to-do list in a detail-oriented tree. Being that I've been a user of Microsoft's To-Do for a long time, allowing it to run my life for me, nearly, I'll be basing the functionality on that.  
*Anything I've struckthrough here is being reserved for a future version.*

## MVP:
The minimum viable product for this project will be that I'm able to produce a webpage that displays separately a collection of task lists, and a collection of tasks from the currently selected list. Each list internally should be handled as a task itself. Tasks must be able to be completed, have their priority shifted, added and removed. You should be able to change the text of a task.  
The big difference from Microsoft's style is that I intend to use a many-to-many relationship system. Each task can be promoted to a list or a category, or demoted to a step of another task without losing any of the other associated data. Tasks can be 'children' of multiple lists. Subtasks are themselves full-fledged tasks, not just togglable strings. Categories and lists can be 'completed' just like tasks and will be separated from uncompleted collections accordingly.

## Feature List / Checklist:
- [x] Refactor Login class and MySQL interface layer (backend)
- [x] Create Login page for testing
- [ ] ~~Add 2FA functionality compatible with standard authenticator apps~~
- [x] Build basic webpage with static sidebar
  - [x] Sidebar lists tasks upgraded to "Lists"
  - [x] Lists can be categorized as well
  - [ ] Main screen:
    - [x] Should show "category"-class tasks as potentially collapsible group
    - [x] Should display only categories that are children of selected list
    - [x] Categories should only display tasks that are children
    - [ ] Categories should be re-arrangable, optional (if user needs to do List->A->1, then List->B->1, then List->A->2 in order, the applet should allow that arrangement)<sup>1</sup>
    - [x] Separate view should be made visible when a task (of any classification) is selected
  - [ ] ~~Should have an automatic "My Day" list that allows alternate grouping<sup>1</sup> and which suggests new tasks, collects tasks due same-day, and retains tasks due yesterday in-order, automatically.~~
  - [ ] ~~In PWA mode should support swipe-actions (swipe left to delete, swipe right to add to your day, etc)(configurable)~~
- [ ] ~~Consider possibility of making lists accessible to multiple people for group projects~~
- [ ] Readme needs to explain how to clone and run
- [x] Should have the option for categories in any list-like placement
- [ ] Add/Remove/Edit anything.

##### Tasks should...
- [ ] Have 'completion' boolean
- [ ] ~~Have 'urgent' boolean~~
- [x] Permit string up to (256? 512?) characters as primary descriptor
- [x] Have 'priority' or 'order' within list/category
- [x] Be able to have multiple parents and multiple children (conflicts with previous, still doable)
- [ ] ~~Support long-form explanations in a textbox field which would store it's data on a separate table with linked indexes to minimize storage allocations~~
- [ ] ~~Have an 'Add to My Day' button~~
- [ ] ~~Have reminders (either through push (PWA?) or via. email)~~
- [ ] ~~Have due-dates, times, possibility for multiple?~~
- [ ] ~~Repeat options (don't litter the table with complete instances, just change due-date (etc) and mark incomplete whenever marked completed~~
- [ ] ~~Support attatchments? Images. Maybe.~~
- [ ] ~~Store created, modified, completed datetime~~
- [ ] ~~Be automatically **pruned** after a fixed period of time after completion to reduce wasted memory in database~~
- [ ] ~~Automatic child deletion should be configurable so that lists exported for protection can be imported for viewing without being automatically deleted. Should be able to be disabled per item (bitflag?)~~

##### Lists should...
- [ ] ~~Support automatic sort options without losing custom order~~
- [ ] ~~Be able to be imported, exported, shared, duplicated (markdown or proprietary)~~

<sub>this will probably be the last time I make a feature list in this style, being that this project will replace the need for them</sub>
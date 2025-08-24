# task-tree
This will be my "first" personal project produced for Boot.dev. I'd prefer to keep this closed-source, so this repo will be moved to my private GitLab as soon as I'm able, but the course expects that I produce open source code. I assume I'll be graded on it at some point, and I can only hope it doesn't get crawled in the mean time. Regardless: the intent is to first produce a refactor of my old xLib project, adding 2FA and some other features, cleaning it up, and to produce from that a tool that tracks tasks like a to-do list in a detail-oriented tree. Being that I've been a user of Microsoft's To-Do for a long time, allowing it to run my life for me, nearly, I'll be basing the functionality on that.

## MVP:
The minimum viable product of this project will be that I'm able to produce a webpage with buttons that enable interaction with a list of different "task" items. Tasks should have name strings, completion toggles, urgency markers, and a priority ranking in the list. As a major change to Microsoft's style, I intend to change from their system of holding subtasks as simple strings to a multi-parent system. Each task can be promoted to a list or a category, or demoted to a step of another task without losing any of the other associated data. Subtasks can have longform comments, explanations, urgency, due-dates, etc. Categories and lists can be 'completed' and will be separated from uncompleted collections accordingly. The whole tool should be able to run as a PWA additionally.

## Feature List / Checklist:
- [ ] Refactor Login class and MySQL interface layer (backend)
- [ ] Create Login page for testing
- [ ] Add 2FA functionality compatible with standard authenticator apps
- [ ] Build basic webpage with static sidebar
  - [ ] Sidebar lists tasks upgraded to "Lists"
  - [ ] Lists can be categorized as well
  - [ ] Main screen:
    - [ ] Should show "category"-class tasks as potentially collapsible group
    - [ ] Should display only categories that are children of selected list
    - [ ] Categories should only display tasks that are children
    - [ ] Categories should be re-arrangable, optional (if user needs to do List->A->1, then List->B->1, then List->A->2 in order, the applet should allow that arrangement)<sup>1</sup>
    - [ ] Separate view should be made visible when a task (of any classification) is selected
  - [ ] Should have an automatic "My Day" list that allows alternate grouping<sup>1</sup> and which suggests new tasks, collects tasks due same-day, and retains tasks due yesterday in-order, automatically.
  - [ ] In PWA mode should support swipe-actions (swipe left to delete, swipe right to add to your day, etc)(configurable)
- [ ] Consider possibility of making lists accessible to multiple people for group projects
- [ ] Readme needs to explain how to clone and run
- [ ] Should have the option for categories in any list-like placement

##### Tasks should...
- [ ] Have 'completion' boolean
- [ ] Have 'urgent' boolean
- [ ] Permit string up to (256? 512?) characters as primary descriptor
- [ ] Have 'priority' or 'order' within list/category
- [ ] Be able to have multiple parents and multiple children (conflicts with previous, still doable)
- [ ] Support long-form explanations in a textbox field which would store it's data on a separate table with linked indexes to minimize storage allocations
- [ ] Have an 'Add to My Day' button
- [ ] Have reminders (either through push (PWA?) or via. email)
- [ ] Have due-dates, times, possibility for multiple?
- [ ] Repeat options (don't litter the table with complete instances, just change due-date (etc) and mark incomplete whenever marked completed
- [ ] Support attatchments? Images. Maybe.
- [ ] Store created, modified, completed datetime
- [ ] Be automatically **pruned** after a fixed period of time after completion to reduce wasted memory in database
- [ ] Automatic child deletion should be configurable so that lists exported for protection can be imported for viewing without being automatically deleted. Should be able to be disabled per item (bitflag?)

##### Lists should...
- [ ] Support automatic sort options without losing custom order
- [ ] Be able to be imported, exported, shared, duplicated (markdown or proprietary)

<sub>this will probably be the last time I make a feature list in this style, being that this project will replace the need for them</sub>

## Final Note:
Ahead of actually starting this project I want to say, mainly to myself: ***DO NOT EXPECT POLISH.*** The project guideline says it should take 20-40 hours. Don't make this a two month task for yourself, it doesn't need to be beautiful, it only needs to work. The quiet, secondary goal will be to integrate this into the homepage that doesn't currently exist as part of the backend. And then *someday* we might reach for the tertiary goal of releasing it as an app. For now though: just target MVP and move on. The checklist above could be easy or it could be very hard, and we know that. If'n ye wanna finish Boot.Dev before the subscription runs out, then make it snappy.  
Maybe I'll be able to convince the course to accept a link to the gitlab, or I'll make this private after I've submitted it and wait to be contacted on the matter. It's my one pet peeve about software development: If you want to make money on your creations *it seems* you need to guard your thoughts like gold. I hope someday to be proven wrong on that.
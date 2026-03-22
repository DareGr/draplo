# Project Management Tool — Domain Context

## Overview

This is a Kanban-based project management platform for teams. The core experience revolves around visual boards with draggable task cards, similar to Trello or Linear. Teams create projects, organize work into boards with columns representing workflow stages, and track progress through task assignments, due dates, and time logging.

## Domain Terminology

- **Project** — A top-level container representing a body of work (e.g., "Website Redesign", "Q2 Marketing Campaign"). Projects have start/end dates, a status (active, on hold, completed, archived), and a team of assigned members.
- **Board** — A Kanban board within a project. Most projects have one default board, but complex projects may use multiple boards (e.g., "Development", "Design", "QA"). Each board has its own set of columns.
- **Column** — A stage in the workflow (e.g., "Backlog", "To Do", "In Progress", "Review", "Done"). Columns are ordered left-to-right and support optional WIP (Work In Progress) limits to prevent bottlenecks.
- **Task** — The fundamental unit of work. A task has a title, description (markdown-supported), assignee, priority (urgent, high, medium, low), due date, time estimate, and position within its column. Tasks move between columns via drag-and-drop.
- **Task Comment** — A threaded discussion on a task. Team members use comments to ask questions, share updates, and make decisions. Comments support mentions (@user) to notify specific people.
- **Task Attachment** — A file uploaded to a task. Common attachments include screenshots, design mockups, documents, and spreadsheets. Files are stored on the local filesystem.
- **Team Member** — A user assigned to a project with a role (admin, member, viewer). Project-level roles control what a user can do within that specific project.
- **Time Entry** — A log of time spent on a task. Entries can be manually created or tracked with a start/stop timer. Each entry records duration, description, and whether it is billable.
- **Tag** — A colored label applied to tasks for cross-cutting categorization (e.g., "Bug", "Feature", "Design", "Urgent"). Tags are workspace-wide and can be used to filter tasks across projects.

## Key Business Rules

1. Tasks belong to exactly one column at a time. Moving a task between columns represents a status change and should be logged in an activity feed.
2. WIP limits on columns are advisory by default but can be enforced — when enforced, users cannot drag more tasks into a column that has reached its limit.
3. Task ordering within a column matters and is controlled by a sort_order field. Drag-and-drop reordering updates sort_order for affected tasks.
4. Due date notifications should fire at configurable intervals (e.g., 1 day before, on the day, 1 day overdue) via email.
5. Time entries are tied to both a task and a user. The sum of time entries per task should be comparable to the estimated_hours for progress tracking.
6. Project archiving is a soft operation — archived projects are hidden from the default view but remain accessible. Tasks within archived projects cannot be modified.
7. Board updates must be real-time via WebSockets so that all team members see task movements and changes instantly without refreshing.

## Multi-Tenancy

Each workspace is a tenant. A single instance can host multiple organizations, each with their own projects, boards, members, and data. Users may belong to multiple tenants but switch between them explicitly. Tenant isolation ensures one organization never sees another's projects or tasks.

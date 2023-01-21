# Quest
Opening the source from one of my cancelled commission.

Demo video: https://www.youtube.com/watch?v=_aDHyfgpFiE
# Features
- Config-customizable quests.
- Categorizable quests.
- NPC dialogue with popping text.

## [Example quest config](https://github.com/Endermanbugzjfc/Quest/blob/18a61d4c14d73971e5d9248ac76a64bde0452596/resources/quests/farming/15.yml)
```yaml
name: Harvest 750 carrots and 100 potatoes
dialog:
  title: Farmer
  content:
    |-
    Harvest 750 carrots, 100 potatoes and come back.
  buttons:
    - label: Claim
      quest:button_remove: [ ]
task:
  # quest:collect = Remove the item once obtain
  quest:obtain:
    minecraft:carrot:
      amount: 750
    minecraft:potato:
      amount: 100
reward:
  quest:commands:
    - givemoney {player} 5000
```
## Task types
# API
- [Register](https://github.com/Endermanbugzjfc/Quest/blob/18a61d4c14d73971e5d9248ac76a64bde0452596/src/Endermanbugzjfc/Quest/Quest.php#L83-L87) your [TaskInterface](https://github.com/Endermanbugzjfc/Quest/blob/master/src/Endermanbugzjfc/Quest/tasks/TaskInterface.php) to add custom tasks.
# Potential problems
- The popping text of NPC dialogue might bloat the server network and lag players. There is currently NO interface to disable the popping text. (But a "Skip" button is provided to players.)

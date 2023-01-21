-- #!sqlite
-- #{ init
-- #    { player_quest_progress
CREATE TABLE IF NOT EXISTS player_quest_progress
(
    uuid             BINARY(16)   NOT NULL,
    quest_identifier VARCHAR(255) NOT NULL,
    task_data        TEXT                  DEFAULT NULL,
    dialog_data      TEXT                  DEFAULT NULL,
    completed        BOOLEAN      NOT NULL DEFAULT FALSE,
    version          BOOLEAN      NOT NULL, -- Basically unsigned tinyint in SQLite

    PRIMARY KEY (uuid, quest_identifier)
);
-- #    }
-- #}
-- #{ progress
-- #    { get_all
-- #      :uuid string
SELECT quest_identifier, task_data, dialog_data, completed, version
FROM player_quest_progress
WHERE uuid = :uuid;
-- #    }
-- #    { create
-- #      :uuid string
-- #      :quest_identifier string
-- #      :version int
INSERT OR
REPLACE
INTO player_quest_progress
    (uuid, quest_identifier, version)
VALUES (:uuid, :quest_identifier, :version);
-- #    }
-- #    { set_task
-- #      :uuid string
-- #      :quest_identifier string
-- #      :task_data string
UPDATE player_quest_progress
SET task_data = :task_data
WHERE uuid = :uuid
  AND quest_identifier = :quest_identifier;
-- #    }
-- #    { set_dialog
-- #      :uuid string
-- #      :quest_identifier string
-- #      :dialog_data string
UPDATE player_quest_progress
SET dialog_data = :dialog_data
WHERE uuid = :uuid
  AND quest_identifier = :quest_identifier;

-- #    }
-- #    { remove
-- #      :uuid string
-- #      :quest_identifier string
DELETE
FROM player_quest_progress
WHERE uuid = :uuid
  AND quest_identifier = :quest_identifier;
-- #    }
-- #    { set_completed
-- #      :uuid string
-- #      :quest_identifier string
-- #      :completed int default 1
UPDATE player_quest_progress
SET completed = :completed
WHERE uuid = :uuid
  AND quest_identifier = :quest_identifier;
-- #    }
-- #}

CREATE FULLTEXT INDEX IF NOT EXISTS ft_chapters ON chapters (title, content);
CREATE FULLTEXT INDEX IF NOT EXISTS ft_notes ON notes (title, content);
CREATE FULLTEXT INDEX IF NOT EXISTS ft_characters ON characters (name, description);
CREATE FULLTEXT INDEX IF NOT EXISTS ft_acts ON acts (title, content);
CREATE FULLTEXT INDEX IF NOT EXISTS ft_elements ON elements (title, content);

UPDATE files
SET filepath = REPLACE(filepath, 'api/uploads/', 'uploads/')
WHERE filepath LIKE 'api/uploads/%';

-- Verificar resultado:
SELECT id, filepath
FROM files
WHERE filepath LIKE '%uploads%'
LIMIT 10;

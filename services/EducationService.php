<?php

class EducationService
{
    public static function articleRules(): array
    {
        return [
            'title' => 'required|max:180',
            'category' => 'required|in:' . implode(',', array_keys(labels('article_category'))),
            'summary' => 'required|max:300',
            'content' => 'required|min:20',
            'warning_highlight' => 'max:255',
            'tags' => 'max:255',
        ];
    }

    public static function articleLabels(): array
    {
        return [
            'title' => 'Judul artikel',
            'category' => 'Kategori',
            'summary' => 'Ringkasan',
            'content' => 'Isi artikel',
            'warning_highlight' => 'Catatan penting',
            'tags' => 'Kata kunci',
        ];
    }

    public static function interactionRules(): array
    {
        return [
            'substance_a' => 'required|max:120',
            'substance_b' => 'required|max:120',
            'severity_level' => 'required|in:' . implode(',', array_keys(labels('interaction_severity'))),
            'interaction_effect' => 'required|min:10',
            'recommendation' => 'required|min:10',
        ];
    }

    public static function interactionLabels(): array
    {
        return [
            'substance_a' => 'Obat atau golongan',
            'substance_b' => 'Pemicu interaksi',
            'severity_level' => 'Tingkat risiko',
            'interaction_effect' => 'Dampak interaksi',
            'recommendation' => 'Saran jeda atau tindakan',
        ];
    }

    public static function listArticles(string $category = '', string $search = '', bool $publishedOnly = true): array
    {
        $sql = 'SELECT a.*, u.full_name AS author_name FROM education_articles a JOIN users u ON u.id = a.author_id WHERE 1=1';
        $params = [];

        if ($publishedOnly) {
            $sql .= ' AND a.is_published = 1';
        }

        if ($category !== '' && array_key_exists($category, labels('article_category'))) {
            $sql .= ' AND a.category = ?';
            $params[] = $category;
        }

        if ($search !== '') {
            $like = '%' . $search . '%';
            $sql .= ' AND (a.title LIKE ? OR a.summary LIKE ? OR a.tags LIKE ? OR a.content LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }

        $sql .= ' ORDER BY a.is_published DESC, COALESCE(a.published_at, a.created_at) DESC, a.id DESC';

        return db_all($sql, $params);
    }

    public static function findArticleBySlug(string $slug, bool $publishedOnly = true): ?array
    {
        $sql = 'SELECT a.*, u.full_name AS author_name FROM education_articles a JOIN users u ON u.id = a.author_id WHERE a.slug = ?';
        $params = [$slug];

        if ($publishedOnly) {
            $sql .= ' AND a.is_published = 1';
        }

        return db_one($sql, $params);
    }

    public static function findArticle(int $id): ?array
    {
        return db_one('SELECT * FROM education_articles WHERE id = ?', [$id]);
    }

    public static function saveArticle(int $authorId, array $data, int $articleId = 0): int
    {
        $published = !empty($data['is_published']) ? 1 : 0;
        $slug = self::uniqueSlug($data['title'], $articleId);

        if ($articleId > 0) {
            $existing = self::findArticle($articleId);
            if ($existing === null) {
                throw new DomainException('Artikel tidak ditemukan.');
            }
            $publishedAt = $published ? ($existing['published_at'] ?: now_string()) : null;

            db_run(
                'UPDATE education_articles
                 SET title = ?, slug = ?, category = ?, summary = ?, content = ?, warning_highlight = ?, tags = ?, is_published = ?, published_at = ?
                 WHERE id = ?',
                [
                    $data['title'],
                    $slug,
                    $data['category'],
                    $data['summary'],
                    $data['content'],
                    $data['warning_highlight'] !== '' ? $data['warning_highlight'] : null,
                    $data['tags'] !== '' ? $data['tags'] : null,
                    $published,
                    $publishedAt,
                    $articleId,
                ]
            );

            return $articleId;
        }

        return db_insert(
            'INSERT INTO education_articles (author_id, title, slug, category, summary, content, warning_highlight, tags, is_published, published_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $authorId,
                $data['title'],
                $slug,
                $data['category'],
                $data['summary'],
                $data['content'],
                $data['warning_highlight'] !== '' ? $data['warning_highlight'] : null,
                $data['tags'] !== '' ? $data['tags'] : null,
                $published,
                $published ? now_string() : null,
            ]
        );
    }

    public static function deleteArticle(int $articleId): void
    {
        db_run('DELETE FROM education_articles WHERE id = ?', [$articleId]);
    }

    public static function listInteractions(string $search = '', bool $publishedOnly = true): array
    {
        $sql = 'SELECT d.*, u.full_name AS author_name FROM drug_interactions d JOIN users u ON u.id = d.author_id WHERE 1=1';
        $params = [];

        if ($publishedOnly) {
            $sql .= ' AND d.is_published = 1';
        }

        if ($search !== '') {
            $like = '%' . $search . '%';
            $sql .= ' AND (d.substance_a LIKE ? OR d.substance_b LIKE ? OR d.interaction_effect LIKE ? OR d.recommendation LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }

        $sql .= " ORDER BY FIELD(d.severity_level, 'tinggi', 'sedang', 'rendah'), d.substance_a";

        return db_all($sql, $params);
    }

    public static function findInteraction(int $id): ?array
    {
        return db_one('SELECT * FROM drug_interactions WHERE id = ?', [$id]);
    }

    public static function saveInteraction(int $authorId, array $data, int $interactionId = 0): int
    {
        $published = !empty($data['is_published']) ? 1 : 0;

        if ($interactionId > 0) {
            db_run(
                'UPDATE drug_interactions
                 SET substance_a = ?, substance_b = ?, severity_level = ?, interaction_effect = ?, recommendation = ?, is_published = ?
                 WHERE id = ?',
                [
                    $data['substance_a'],
                    $data['substance_b'],
                    $data['severity_level'],
                    $data['interaction_effect'],
                    $data['recommendation'],
                    $published,
                    $interactionId,
                ]
            );

            return $interactionId;
        }

        return db_insert(
            'INSERT INTO drug_interactions (author_id, substance_a, substance_b, severity_level, interaction_effect, recommendation, is_published)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $authorId,
                $data['substance_a'],
                $data['substance_b'],
                $data['severity_level'],
                $data['interaction_effect'],
                $data['recommendation'],
                $published,
            ]
        );
    }

    public static function deleteInteraction(int $interactionId): void
    {
        db_run('DELETE FROM drug_interactions WHERE id = ?', [$interactionId]);
    }

    public static function getContextualArticlesForPatient(int $patientId, int $limit = 3): array
    {
        $hasAntibiotic = (bool) db_value(
            "SELECT COUNT(*) FROM medications WHERE patient_id = ? AND status = 'active' AND is_antibiotic = 1",
            [$patientId]
        );

        if ($hasAntibiotic) {
            return db_all(
                "SELECT id, title, slug, category, summary, warning_highlight
                 FROM education_articles
                 WHERE is_published = 1
                 ORDER BY category = 'antibiotik' DESC, category = 'interaksi_obat' DESC, COALESCE(published_at, created_at) DESC
                 LIMIT " . (int) $limit
            );
        }

        return db_all(
            "SELECT id, title, slug, category, summary, warning_highlight
             FROM education_articles
             WHERE is_published = 1
             ORDER BY category = 'aturan_pakai' DESC, COALESCE(published_at, created_at) DESC
             LIMIT " . (int) $limit
        );
    }

    public static function getArticlesForMedication(array $medication, int $limit = 3): array
    {
        $category = !empty($medication['is_antibiotic']) ? 'antibiotik' : 'aturan_pakai';

        return db_all(
            'SELECT id, title, slug, category, summary, warning_highlight
             FROM education_articles
             WHERE is_published = 1
             ORDER BY category = ? DESC, COALESCE(published_at, created_at) DESC
             LIMIT ' . (int) $limit,
            [$category]
        );
    }

    private static function uniqueSlug(string $title, int $exceptId = 0): string
    {
        $base = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $title), '-'));
        $base = $base !== '' ? substr($base, 0, 180) : 'artikel';
        $slug = $base;
        $counter = 2;

        while (db_value('SELECT COUNT(*) FROM education_articles WHERE slug = ? AND id <> ?', [$slug, $exceptId]) > 0) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}

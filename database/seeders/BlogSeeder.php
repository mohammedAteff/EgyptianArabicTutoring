<?php

namespace Database\Seeders;

use App\Domains\Administration\Models\Administrator;
use App\Domains\CMS\Models\Blog;
use Illuminate\Database\Seeder;

class BlogSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Administrator::first();
        if (! $admin) {
            return;
        }

        if (Blog::count() === 0) {
            Blog::create([
                'title' => 'Mastering Egyptian Arabic: Practical Tips for Beginners',
                'slug' => 'mastering-egyptian-arabic-practical-tips-for-beginners',
                'excerpt' => 'Discover foundational tips, authentic vocabulary, and core phrases to start speaking Egyptian Arabic with confidence.',
                'body' => '<p>Welcome to our comprehensive guide for learning Egyptian Arabic.</p><p>Consistency, immersive listening, and regular speaking practice are the keys to conversational fluency.</p>',
                'status' => 'published',
                'published_at' => now('UTC'),
                'author_id' => $admin->id,
                'locale' => 'en',
                'lock_version' => 1,
            ]);
        }
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('knowledge_categories', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26)->index();
            $table->char('parent_id', 26)->nullable()->index();
            $table->string('name', 255);
            $table->string('slug', 255);
            $table->text('description')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations');

            $table->unique(['organization_id', 'slug']);
        });

        // Add self-referential FK after table + primary key is fully established
        Schema::table('knowledge_categories', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('knowledge_categories')->nullOnDelete();
        });

        Schema::create('knowledge_articles', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26)->index();
            $table->char('pemda_id', 26)->index();
            $table->char('category_id', 26)->nullable()->index();
            $table->string('title', 500);
            $table->text('content')->nullable();
            $table->text('excerpt')->nullable();
            $table->string('knowledge_type', 30)->default('wiki');
            $table->string('status', 30)->default('draft');
            $table->integer('version_number')->default(1);
            $table->char('parent_article_id', 26)->nullable()->index();
            $table->boolean('is_latest')->default(true);
            $table->string('embedding_id', 100)->nullable();
            $table->string('embedding_model', 100)->nullable();
            $table->timestamp('embedded_at')->nullable();
            $table->text('ai_summary')->nullable();
            $table->json('tags')->nullable();
            $table->integer('view_count')->default(0);
            $table->integer('helpful_count')->default(0);
            $table->smallInteger('data_classification')->default(2);
            $table->char('author_id', 26)->index();
            $table->char('published_by', 26)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->char('created_by', 26)->index();
            $table->char('updated_by', 26)->nullable();
            $table->char('deleted_by', 26)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->foreign('pemda_id')->references('id')->on('organizations');
            $table->foreign('category_id')->references('id')->on('knowledge_categories')->nullOnDelete();
            $table->foreign('author_id')->references('id')->on('users');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('published_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['organization_id', 'status']);
        });

        // Add self-referential FK after table + primary key is fully established
        Schema::table('knowledge_articles', function (Blueprint $table) {
            $table->foreign('parent_article_id')->references('id')->on('knowledge_articles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_articles');
        Schema::dropIfExists('knowledge_categories');
    }
};

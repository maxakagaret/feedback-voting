<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 16)->default('member')->after('email');
        });

        // Ideas board: anyone proposes, everyone upvotes, admins move ideas through the roadmap.
        Schema::create('ideas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 140);
            $table->text('description')->nullable();
            $table->string('status', 16)->default('new')->index();
            // Order inside a roadmap column; null outside the roadmap.
            $table->unsignedInteger('position')->nullable();
            // Denormalised counter, kept in sync transactionally with idea_votes.
            $table->unsignedInteger('votes_count')->default(0);
            $table->timestamps();
            $table->index(['status', 'votes_count']);
        });

        Schema::create('idea_votes', function (Blueprint $table) {
            $table->foreignId('idea_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at');
            $table->primary(['idea_id', 'user_id']); // one vote per person, enforced by the database
        });

        Schema::create('idea_status_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idea_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by')->constrained('users')->cascadeOnDelete();
            $table->string('from_status', 16);
            $table->string('to_status', 16);
            $table->string('comment', 500)->nullable();
            $table->timestamp('created_at');
        });

        // Votings: an admin asks "what should we build next?" with a fixed set of options.
        Schema::create('votings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title', 140);
            $table->text('description')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamp('closed_at')->nullable(); // closed early by an admin
            $table->unsignedTinyInteger('max_choices')->default(1);
            $table->string('results_visibility', 16)->default('after_vote');
            $table->timestamps();
        });

        Schema::create('voting_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('idea_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label', 140);
            $table->unsignedSmallInteger('position');
        });

        // One ballot per voter per voting; its choices can be replaced while the voting is open.
        Schema::create('ballots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['voting_id', 'user_id']);
        });

        Schema::create('ballot_choices', function (Blueprint $table) {
            $table->foreignId('ballot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voting_option_id')->constrained()->cascadeOnDelete();
            $table->primary(['ballot_id', 'voting_option_id']);
        });
    }

    public function down(): void
    {
        foreach (['ballot_choices', 'ballots', 'voting_options', 'votings', 'idea_status_changes', 'idea_votes', 'ideas'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('role'));
    }
};

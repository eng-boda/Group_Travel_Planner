<?php

require_once __DIR__ . '/../model/poll.php';
require_once __DIR__ . '/../model/poll_option.php';
require_once __DIR__ . '/../model/vote.php';
require_once __DIR__ . '/../controller/MemberController.php';

class VotingController {

    private $pollModel;
    private $optionModel;
    private $voteModel;

    public function __construct() {
        $this->pollModel   = new Poll();
        $this->optionModel = new PollOption();
        $this->voteModel   = new Vote();
    }

    /** Create poll + options. Returns array with success flag. */
    public function createPoll($trip_id, $question, $deadline, array $options) {
        $question = trim($question);
        if (empty($question) || count(array_filter(array_map('trim', $options))) < 2) {
            return ['success' => false, 'message' => 'A question and at least 2 options are required.'];
        }

        $this->pollModel->trip_id  = $trip_id;
        $this->pollModel->question = $question;
        $this->pollModel->deadline = $deadline;

        $poll_id = $this->pollModel->createPoll();
        if (!$poll_id) {
            return ['success' => false, 'message' => 'Failed to create poll.'];
        }

        foreach ($options as $opt) {
            $opt = trim($opt);
            if ($opt !== '') {
                $this->optionModel->addOption($poll_id, $opt);
            }
        }

        return ['success' => true, 'poll_id' => $poll_id];
    }

    /** Cast a new vote; organizer weight = 2, member weight = 1. */
    public function castVote($poll_id, $option_id, $user_id, $trip_id) {
        $mc     = new MemberController();
        $weight = $mc->isOrganizer($user_id, $trip_id) ? 2 : 1;

        $result = $this->voteModel->castVote($poll_id, $option_id, $user_id, $weight);

        if ($result === 'already_voted') {
            return ['success' => false, 'message' => 'You have already voted on this poll.'];
        }
        return $result
            ? ['success' => true]
            : ['success' => false, 'message' => 'Failed to record vote.'];
    }

    /** Change an existing vote to a different option (weight stays the same). */
    public function changeVote($poll_id, $new_option_id, $user_id) {
        $result = $this->voteModel->changeVote($poll_id, $new_option_id, $user_id);
        return $result
            ? ['success' => true]
            : ['success' => false, 'message' => 'Failed to change vote.'];
    }

    /**
     * Get all polls for a trip enriched with:
     *  - results  (vote_count, total_weight, organizer_count, organizer_names per option)
     *  - grand_total_weight  — sum of all weights across all options (used for % bar)
     *  - total_votes         — plain head count across all options
     *  - user_voted          — option_id the current user picked, or null
     */
    public function getPollsWithResults($trip_id, $user_id) {
        $polls = $this->pollModel->getPollsByTrip($trip_id);
        $data  = [];

        foreach ($polls as $poll) {
            $pid            = (int) $poll['poll_id'];
            $results        = $this->voteModel->getResults($pid);
            $user_voted_opt = $this->voteModel->getUserVote($pid, $user_id);

            $grand_total_weight = 0;
            $total_votes        = 0;

            foreach ($results as $r) {
                $grand_total_weight += (int) $r['total_weight'];
                $total_votes        += (int) $r['vote_count'];
            }

            foreach ($results as &$r) {
                $r['organizer_names'] = (int) $r['organizer_count'] > 0
                    ? $this->voteModel->getOrganizerVoters($pid, (int) $r['option_id'])
                    : [];
            }
            unset($r);

            $data[] = [
                'poll'               => $poll,
                'results'            => $results,
                'grand_total_weight' => $grand_total_weight,
                'total_votes'        => $total_votes,
                'user_voted'         => $user_voted_opt,
            ];
        }
        return $data;
    }

    public function deletePoll($poll_id) {
        return $this->pollModel->deletePoll($poll_id);
    }
}
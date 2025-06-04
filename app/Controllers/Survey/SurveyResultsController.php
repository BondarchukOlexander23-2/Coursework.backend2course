<?php

/**
 * Простий SurveyResultsController
 * Створіть файл app/Controllers/Survey/SurveyResultsController.php
 */
class SurveyResultsController extends BaseController
{
    private $validator;

    public function __construct()
    {
        parent::__construct();
        $this->validator = new SurveyValidator();
    }

    /**
     * Показати результати опитування або квізу
     */
    public function results(): void
    {
        $this->safeExecute(function() {
            $surveyId = $this->getIntParam('id');
            $responseId = $this->getIntParam('response');

            $survey = $this->validator->validateAndGetSurvey($surveyId);
            if (!$survey) {
                throw new NotFoundException('Опитування не знайдено');
            }

            $questions = Question::getBySurveyId($surveyId);
            $isQuiz = Question::isQuiz($surveyId);

            if ($isQuiz) {
                $this->showQuizResults($survey, $questions, $responseId);
            } else {
                $this->showSurveyResults($survey, $questions);
            }
        });
    }

    /**
     * Показати результати квізу
     */
    private function showQuizResults(array $survey, array $questions, int $responseId): void
    {
        $quizStats = SurveyResponse::getQuizStats($survey['id']);
        $topResults = SurveyResponse::getTopResults($survey['id'], 10);
        $userResult = null;

        if ($responseId > 0) {
            $userResult = SurveyResponse::findById($responseId);
        }

        // Завантажуємо View
        if (!class_exists('SurveyResultsView')) {
            require_once __DIR__ . '/../../Views/Survey/SurveyResultsView.php';
        }

        $view = new SurveyResultsView([
            'title' => 'Результати квізу',
            'survey' => $survey,
            'questions' => $questions,
            'isQuiz' => true,
            'stats' => $quizStats,
            'topResults' => $topResults,
            'userResult' => $userResult
        ]);

        $content = $view->render();

        // Результати квізу кешуємо на 15 хвилин
        $this->responseManager
            ->setCacheHeaders(900)
            ->sendSuccess($content);
    }

    /**
     * Показати результати звичайного опитування
     */
    private function showSurveyResults(array $survey, array $questions): void
    {
        $questionStats = [];
        foreach ($questions as $question) {
            $questionStats[$question['id']] = QuestionAnswer::getQuestionStats($question['id']);
        }

        $totalResponses = SurveyResponse::getCountBySurveyId($survey['id']);

        // Завантажуємо View
        if (!class_exists('SurveyResultsView')) {
            require_once __DIR__ . '/../../Views/Survey/SurveyResultsView.php';
        }

        $view = new SurveyResultsView([
            'title' => 'Результати опитування',
            'survey' => $survey,
            'questions' => $questions,
            'isQuiz' => false,
            'questionStats' => $questionStats,
            'totalResponses' => $totalResponses
        ]);

        $content = $view->render();

        // Результати опитування кешуємо на 30 хвилин
        $this->responseManager
            ->setCacheHeaders(1800)
            ->sendSuccess($content);
    }
}
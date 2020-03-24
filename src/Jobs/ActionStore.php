<?php

namespace WeblaborMx\Front\Jobs;

use WeblaborMx\Front\Traits\IsRunable;

class ActionStore
{
    use IsRunable;

    public $front;
    public $object;
    public $action;
    public $request;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($front, $object, $action, $request)
    {
        $this->front = $front;
        $this->object = $object;
        $this->action = $action;
        $this->request = $request;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Search the individual action
        $action = $this->front->searchAction($this->action);
        if(!is_object($action)) {
            abort(406, "Action wasn't found: {$this->action}");
        }

        // Dont show action if is not showable
        if(!$action->show) {
            abort(404);
        }

        // Set object to action and validate
        $action = $action->setObject($this->object)->validate();

        // Execute action
        $result = $action->handle($this->object, $this->request);

        // If returns a response so dont do any more
        if($this->isResponse($result)) {
            $this->request->flash();
            return $result;
        }

        // If returns empty so show successfull message
        if(!isset($result)) {
            flash(__(':name action executed successfully', ['name' => $action->title,]))->success();
        }

        // In case they return a flash answer
        $this->request->flash();
    }
}
<?php

namespace WeblaborMx\Front\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use WeblaborMx\Front\Traits\IsRunable;
use WeblaborMx\Front\Jobs\FrontStore;
use WeblaborMx\Front\Jobs\FrontIndex;
use WeblaborMx\Front\Jobs\FrontUpdate;
use WeblaborMx\Front\Jobs\FrontDestroy;
use WeblaborMx\Front\Jobs\ActionShow;
use WeblaborMx\Front\Jobs\ActionStore;

class FrontController extends Controller
{
    use IsRunable;

    private $front;

    public function __construct()
	{
        $this->front = $this->getFront();
    }

    /*
     * CRUD Functions
     */

    public function index()
    {
        $this->authorize('viewAny', $this->front->getModel());

        // Front code
        $front = $this->front->setSource('index');
        $base_url = $front->base_url;

        $response = $this->run(new FrontIndex($front, $base_url));
        if($this->isResponse($response)) {
            return $response;
        }
        
        // Show view
        $objects = $response;
        return view('front::crud.index', compact('objects', 'front'));
    }

    public function create()
	{
        $this->authorize('create', $this->front->getModel());
        
        $front = $this->front->setSource('create');
        return view('front::crud.create', compact('front'));
    }

    public function store(Request $request)
	{
        $this->authorize('create', $this->front->getModel());

        // Front code
        $front = $this->front->setSource('store');
        $response = $this->run(new FrontStore($request, $front));
        if($this->isResponse($response)) {
            return $response;
        }
        
        // Redirect to index page
        return redirect($front->base_url);
    }

    public function show($object)
    {
        // Get object
        $object = $this->getObject($object);
        
        // Validate policy
        $this->authorize('view', $object);

        // Front code
        $front = $this->front->setSource('show')->setObject($object);
        $front->show($object);

        // Show view
        return view('front::crud.show', compact('object', 'front'));
    }

    public function edit($object)
    {
        // Get object
        $object = $this->getObject($object);

        // Validate policy
        $this->authorize('update', $object);

        // Front code
        $front = $this->front->setSource('edit')->setObject($object);

        // Show view
        return view('front::crud.edit', compact('object', 'front'));
    }

    public function update($object, Request $request)
    {
        // Get object
        $object = $this->getObject($object);
        
        // Validate policy
        $this->authorize('update', $object);

        // Front code
        $front = $this->front->setSource('update')->setObject($object);
        $response = $this->run(new FrontUpdate($request, $front, $object));
        if($this->isResponse($response)) {
            return $response;
        }

        // Redirect
        return back();
    }

    public function destroy($object)
    {
        // Get object
        $object = $this->getObject($object);

        // Validate Policy
        $this->authorize('delete', $object);

        // Front code
        $front = $this->front->setSource('show')->setObject($object);
        $response = $this->run(new FrontDestroy($front, $object));
        if($this->isResponse($response)) {
            return $response;
        }

        // Redirect
        return redirect($this->front->base_url);
    }

    /*
     * Actions
     */

    public function actionShow($object, $action) 
    {
        // Get object
        $object = $this->getObject($object);

        // Front code
        $front = $this->front->setSource('create')->setObject($object);
        $response = $this->run(new ActionShow($front, $object, $action, function() use ($object, $action) {
            return $this->actionStore($object->getKey(), $action, request());
        })));
        if($this->isResponse($response)) {
            return $response;
        }

        // Show view
        $action = $response;
        return view('front::crud.action', compact('action', 'front', 'object'));
    }

    public function actionStore($object, $action, Request $request)
    {
        // Get object
        $object = $this->getObject($object);

        // Front code
        $front = $this->front->setSource('create')->setObject($object);
        $response = $this->run(new ActionStore($front, $object, $action, $request));
        if($this->isResponse($response)) {
            return $response;
        }

        // Redirect back
        return back();
    }

    public function indexActionShow($action) 
    {
        $this->authorize('update', $this->front->getModel());
        
        $sport = $this->repository->findSport($sport);
        $sportable = $this->sportable;

        $class = $sport->getClass($this->sportable->db_class);
        $front = getFront($class, 'create')->addData(compact('sport'));
        $action = $this->repository->getIndexAction($action, $front);
        
        return view('front::crud.index-action', compact('action', 'front', 'sportable'));
    }

    public function indexActionStore($action, Request $request)
    {
        $this->authorize('update', $this->front->getModel());

        $sport = $this->repository->findSport($sport);
        $class = $sport->getClass($this->sportable->db_class);
        $front = getFront($class, 'create')->addData(compact('sport'));
        $action = $this->repository->getIndexAction($action, $front);
        $action->validate();

        $result = $action->handle($request);
        if(!isset($result)) {
            $message = config('front.messages.action_sucess');
            $message = str_replace('{title}', $action->title, $message);
            flash($message)->success();
        } else {
            $request->flash();
        }
        
        return back();
    }

    /*
     * More features
     */

    public function lenses($lense, Request $request)
    {
        $this->authorize('viewAny', $this->front->getModel());

        // Front code
        $front = $this->front->setSource('index');
        $objects = $this->repository->index($front)->getLense($lense);
        if(get_class($objects)!='Illuminate\Pagination\LengthAwarePaginator') {
            return $objects;
        }
        return view('front::crud.index', compact('objects', 'front'));
    }

    public function search(Request $request)
    {
        $title = $this->front->title;
        $result = $this->front->globalIndexQuery();

        // Get query if sent
        if($request->filled('filter_query')) {
            $query = json_decode($request->filter_query);
            $query = unserialize($query);
            $query = $query->getClosure();
            $result = $query($result);
        }
        
        $result  = $result->search($request->term)->limit(10)->get()->map(function($item) use ($title) {
            return [
                'label' => $item->$title, 
                'id' => $item->getKey(), 
                'value' => $item->$title 
            ];
        })->sortBy('label');
        print json_encode($result);
    }

    /*
     * Internal Functions
     */

    private function getFront()
    {
        $action = request()->route()->getAction();
        if(!is_array($action) || !isset($action['prefix'])) {
            return;
        }
        $action = explode('/', $action['prefix']);
        $action = $action[count($action)-1];
        $action = Str::camel(Str::singular($action));
        $action = ucfirst($action);
        $class = 'App\Front\\'.$action;
        return new $class;
    }

    private function getObject($object)
    {
        $model = $this->front->getModel();
        $object = $model::find($object);
        if(!is_object($object)) {
            abort(404);
        }
        return $object;
    }
}

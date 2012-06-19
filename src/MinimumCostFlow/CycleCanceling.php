<?php

namespace Fhaculty\Graph\Algorithm\MinimumCostFlow;

use Fhaculty\Graph\Exception\UnexpectedValueException;

use Fhaculty\Graph\Exception\UnderflowException;

use Fhaculty\Graph\Exception\RuntimeException;

use Fhaculty\Graph\Edge;
use Fhaculty\Graph\Algorithm\MaxFlow\EdmondsKarp as MaxFlowEdmondsKarp;
use Fhaculty\Graph\Algorithm\DetectNegativeCycle;
use Fhaculty\Graph\Algorithm\ResidualGraph;

class CycleCanceling extends Base {

    public function createGraph() {
    	$this->checkBalance();

        // create resulting graph with supersource and supersink
        $resultGraph = $this->graph->createGraphClone();

        $superSource = $resultGraph->createVertex()->setLayout('label','s*');
        $superSink   = $resultGraph->createVertex()->setLayout('label','t*');

        $sumBalance = 0;

        // connect supersource s* and supersink t* with all "normal" sources and sinks
        foreach($resultGraph->getVertices() as $vertex){
            $flow = $vertex->getBalance(); //$vertex->getFlow();
            $b = abs($vertex->getBalance());
            if($flow > 0){ // source
                $superSource->createEdgeTo($vertex)->setCapacity($b);

                $sumBalance += $flow;
            }else if($flow < 0){ // sink
                $vertex->createEdgeTo($superSink)->setCapacity($b);
            }
        }

        // calculate (s*,t*)-flow
        $algMaxFlow = new MaxFlowEdmondsKarp($superSource,$superSink);
        $flow = $algMaxFlow->getFlowMax();

        if($flow !== $sumBalance){
            throw new UnexpectedValueException('(s*,t*)-flow of '.$flow.' has to equal sumBalance '.$sumBalance);
        }


        $resultGraph = $algMaxFlow->createGraph();

        while(true){
            //create residual graph
            $algRG = new ResidualGraph($resultGraph);
            $residualGraph = $algRG->createGraph();

            //get negative cycle
            $alg = new DetectNegativeCycle($residualGraph);
            try {
                $clonedEdges = $alg->getCycleNegative()->getEdges();
            }
            catch (UnderflowException $ignore) {                               // no negative cycle found => end algorithm
                break;
            }

            //calculate maximal possible flow = minimum capacity remaining for all edges
            $newFlow = Edge::getFirst($clonedEdges,Edge::ORDER_CAPACITY_REMAINING)->getCapacityRemaining();

            //set flow on original graph
            $this->addFlow($resultGraph,$clonedEdges,$newFlow);
        }
        
        // destroy temporary supersource and supersink again
        $resultGraph->getVertex($superSink->getId())->destroy();
        $resultGraph->getVertex($superSource->getId())->destroy();

        return $resultGraph;
    }
}
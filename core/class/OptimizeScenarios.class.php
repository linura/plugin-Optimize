<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

require_once('BaseOptimize.class.php');

class OptimizeScenarios extends BaseOptimize
{
    /**
     * Obtenir la liste de tous les scénarios.
     *
     * @return array Tableau contenant la liste des scénarios.
     */
    private function getAll()
    {
        return scenario::all();
    }

    /**
     * Extraire les informations pertinentes d'un scénario.
     *
     * @param mixed $scenario Scénario concernées
     *
     * @return array Informations du scénario
     */
    private function extractInformationsFromScenario($scenario)
    {
        $informations = array();

        $informations['id'] = $scenario->getId();
        $informations['name'] = $scenario->getName();
        $informations['log'] = $scenario->getConfiguration('logmode');
        $informations['syncmode'] = $scenario->getConfiguration('syncmode');
        $informations['enabled'] = $scenario->getIsActive();
//        $informations['consistency'] = $scenario->consystencyCheck(true);
        $informations['last_launch'] = $scenario->getLastLaunch();
        $informations['running_state'] = $scenario->running();
        return $informations;
    }

    /**
     * Evalue les informations d'un scénario.
     *
     * @param array $informations Informations à évaluer
     *
     * @return array Rapport sur les informations évaluées
     */
    private function rateScenarioInformations($informations)
    {
        $rating = array();

        // Valeurs par défaut
        $rating['log'] = 'ok';
        $rating['syncmode'] = 'ok';
        $rating['enabled'] = 'ok';
        $rating['last_launch'] = 'ok';
        self::$bestScore += 4;

        // Les logs doivent être désactivés
        if ($informations['log'] != 'none') {
            self::$badPoints++;
            $rating['log'] = 'warn';
        }

        // Les scénarios doivent être exécutés de façon synchrone
        if ($informations['syncmode'] == 0) {
            self::$badPoints++;
            $rating['syncmode'] = 'warn';
        }

        // Les scénarios doivent être activés
        if ($informations['enabled'] == 0) {
            self::$badPoints++;
            $rating['enabled'] = 'warn';
        }

        $today = new \DateTime('now');
        $lastLaunch = new \DateTime($informations['last_launch']);
        $daysLimit = config::byKey('scenario-days-limit', 'Optimize');
        if ($informations['last_launch'] == '' || $lastLaunch->diff($today)->days > $daysLimit) {
            self::$badPoints++;
            $rating['last_launch'] = 'warn';
        }

        return $rating;
    }

    /**
     * Obtenir les informations et une évaluation de l'ensemble des scénarios.
     *
     * @return array Informations sur l'ensemble des scénarios
     */
    public function getInformations()
    {
        $scenarios = $this->getAll();
        $informations = array();

        foreach ($scenarios as $scenario) {
            $scenarioInformations = $this->extractInformationsFromScenario($scenario);
            $rating = $this->rateScenarioInformations($scenarioInformations);
            $scenarioInformations['rating'] = $rating;
            \array_push($informations, $scenarioInformations);
        }
        return $informations;
    }

    /**
     * Obtenir l'objet d'un scénario à partir de son identifiant.
     *
     * @param integer $scenarioId Identifiant du scénario
     *
     * @return mixed Scénario
     */
    private function getScenarioById($scenarioId)
    {
        return scenario::byId($scenarioId);
    }

    /**
     * Désactiver les logs d'un scénario.
     *
     * @param mixed $scenarioId Identifiant du scénario ou optimize-all
     */
    public function disableLogs($scenarioId)
    {
        if ($scenarioId == 'optimize-all') {
            $scenarios = $this->getAll();
            foreach ($scenarios as $scenario) {
                $scenario->setConfiguration('logmode', 'none');
                $scenario->save();
            }
        } else {
            $scenario = $this->getScenarioById($scenarioId);
            $scenario->setConfiguration('logmode', 'none');
            $scenario->save();
        }
    }

    /**
     * Mettre un scénario en mode synchrone
     *
     * @param integer $scenarioId Identifiant du scénario
     */
    public function setSyncMode($scenarioId)
    {
        $scenario = $this->getScenarioById($scenarioId);
        $scenario->setConfiguration('syncmode', 1);
        $scenario->save();
    }

    /**
     * Supprime un scénario si celui-ci est inactif.
     *
     * @param integer $scenarioId Identifiant du scénario
     */
    public function removeIfDisabled($scenarioId)
    {
        $scenario = $this->getScenarioById($scenarioId);
        if ($scenario->getIsActive() == 0) {
            $this->remove($scenarioId);
        }
    }

    /**
     * Supprime un scénario
     *
     * @param integer $scenarioId Identifiant du scénario
     */
    public function remove($scenarioId) {
        $scenario = $this->getScenarioById($scenarioId);
        $scenario->remove();
    }
}

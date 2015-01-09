<?php

namespace Oro\Bundle\QueryDesignerBundle\Tests\Unit\Grid\DatagridConfigurationBuilder;

use Doctrine\ORM\Query;

use Oro\Bundle\EntityBundle\Provider\VirtualRelationProviderInterface;
use Oro\Bundle\QueryDesignerBundle\Tests\Unit\Fixtures\QueryDesignerModel;
use Oro\Bundle\QueryDesignerBundle\Tests\Unit\OrmQueryConverterTest;

class VirtualRelationsTest extends OrmQueryConverterTest
{
    /**
     * @param array $columns
     * @param array $virtualRelationQuery
     * @param array $expected
     *
     * @dataProvider virtualRelationsDataProvider
     */
    public function testVirtualColumns(array $columns, array $virtualRelationQuery, array $expected)
    {
        $entity = 'Acme\Entity\TestEntity';
        $doctrine = $this->getDoctrine(
            [
                $entity => [],
                'Oro\Bundle\TrackingBundle\Entity\TrackingEvent' => [],
                'Oro\Bundle\TrackingBundle\Entity\TrackingWebsite' => [],
                'Oro\Bundle\TrackingBundle\Entity\Campaign' => [],
                'Oro\Bundle\TrackingBundle\Entity\List' => [],
                'Oro\Bundle\TrackingBundle\Entity\ListItem' => [],
            ]
        );
        $virtualColumnProvider = $this->getVirtualFieldProvider();
        $model = new QueryDesignerModel();
        $model->setEntity($entity);
        $model->setDefinition(json_encode(['columns' => $columns]));
        $builder = $this->createDatagridConfigurationBuilder($model, $doctrine, null, $virtualColumnProvider);

        /** @var \PHPUnit_Framework_MockObject_MockObject|VirtualRelationProviderInterface $virtualRelationProvider */
        $virtualRelationProvider = $this->getMock('Oro\Bundle\EntityBundle\Provider\VirtualRelationProviderInterface');

        $virtualRelationProvider->expects($this->any())
            ->method('isVirtualRelation')
            ->will(
                $this->returnCallback(
                    function ($className, $fieldName) use ($virtualRelationQuery) {
                        return !empty($virtualRelationQuery[$className][$fieldName]);
                    }
                )
            );
        $virtualRelationProvider->expects($this->any())
            ->method('getVirtualRelationQuery')
            ->will(
                $this->returnCallback(
                    function ($className, $fieldName) use ($virtualRelationQuery) {
                        if (empty($virtualRelationQuery[$className][$fieldName])) {
                            return [];
                        }

                        return $virtualRelationQuery[$className][$fieldName];
                    }
                )
            );

        $builder->setVirtualRelationProvider($virtualRelationProvider);

        $this->assertEquals($expected, $builder->getConfiguration()->toArray()['source']['query']);
    }

    /**
     * @return array
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function virtualRelationsDataProvider()
    {
        return [
            'on root entity' => [
                'columns' => [
                    'code' => [
                        'name' => 'trackingEvent+Oro\Bundle\TrackingBundle\Entity\TrackingEvent::code',
                        'label' => 'code',
                    ]
                ],
                'virtualRelationQuery' => [
                    'Acme\Entity\TestEntity' => [
                        'trackingEvent' => [
                            'join' => [
                                'left' => [
                                    [
                                        'join' => 'Oro\Bundle\TrackingBundle\Entity\TrackingEvent',
                                        'alias' => 'trackingEvent',
                                        'conditionType' => 'WITH',
                                        'condition' => 'trackingEvent.code = entity.code'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                'expected' => [
                    'select' => ['t2.code as c1'],
                    'from' => [
                        [
                            'table' => 'Acme\Entity\TestEntity',
                            'alias' => 't1',
                        ]
                    ],
                    'join' => [
                        'left' => [
                            [
                                'join' => 'Oro\Bundle\TrackingBundle\Entity\TrackingEvent',
                                'alias' => 't2',
                                'conditionType' => 'WITH',
                                'condition' => 't2.code = t1.code',
                            ]
                        ]
                    ]
                ]
            ],
            'last in join path' => [
                'columns' => [
                    'website' => [
                        'name' => sprintf(
                            'campaign+%s+%s',
                            'Oro\Bundle\TrackingBundle\Entity\Campaign::trackingEvent',
                            'Oro\Bundle\TrackingBundle\Entity\TrackingEvent::website'
                        ),
                        'label' => 'website',
                    ]
                ],
                'virtualRelationQuery' => [
                    'Oro\Bundle\TrackingBundle\Entity\Campaign' => [
                        'trackingEvent' => [
                            'join' => [
                                'left' => [
                                    [
                                        'join' => 'Oro\Bundle\TrackingBundle\Entity\TrackingEvent',
                                        'alias' => 'trackingEvent',
                                        'conditionType' => 'WITH',
                                        'condition' => 'trackingEvent.code = entity.code'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                'expected' => [
                    'select' => ['t3.website as c1'],
                    'from' => [
                        [
                            'table' => 'Acme\Entity\TestEntity',
                            'alias' => 't1',
                        ]
                    ],
                    'join' => [
                        'left' => [
                            [
                                'join' => 't1.campaign',
                                'alias' => 't2',
                            ],
                            [
                                'join' => 'Oro\Bundle\TrackingBundle\Entity\TrackingEvent',
                                'alias' => 't3',
                                'conditionType' => 'WITH',
                                'condition' => 't3.code = t2.code',
                            ]
                        ]
                    ]
                ]
            ],
            'relation in the middle' => [
                'columns' => [
                    'identifier' => [
                        'name' => sprintf(
                            'campaign+%s+%s+%s',
                            'Oro\Bundle\TrackingBundle\Entity\Campaign::trackingEvent',
                            'Oro\Bundle\TrackingBundle\Entity\TrackingEvent::website',
                            'Oro\Bundle\TrackingBundle\Entity\TrackingWebsite::identifier'
                        ),
                        'label' => 'identifier',
                    ]
                ],
                'virtualRelationQuery' => [
                    'Oro\Bundle\TrackingBundle\Entity\Campaign' => [
                        'trackingEvent' => [
                            'join' => [
                                'left' => [
                                    [
                                        'join' => 'Oro\Bundle\TrackingBundle\Entity\TrackingEvent',
                                        'alias' => 'trackingEvent',
                                        'conditionType' => 'WITH',
                                        'condition' => 'trackingEvent.code = entity.code'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                'expected' => []
            ],
            'multiple joins' => [
                'columns' => [
                    'identifier' => [
                        'name' => sprintf(
                            'ListItem_virtual+%s+%s',
                            'Oro\Bundle\TrackingBundle\Entity\ListItem::List',
                            'Oro\Bundle\TrackingBundle\Entity\List::name'
                        ),
                        'label' => 'name',
                    ]
                ],
                'virtualRelationQuery' => [
                    'Oro\Bundle\TrackingBundle\Entity\ListItem' => [
                        'List' => [
                            'join' => [
                                'left' => [
                                    [
                                        'join' => 'Oro\Bundle\TrackingBundle\Entity\List',
                                        'alias' => 'List',
                                        'conditionType' => 'WITH',
                                        'condition' => 'List.entity = \'Acme\Entity\TestEntity\''
                                    ],
                                    [
                                        'join' => 'Oro\Bundle\TrackingBundle\Entity\ListItem',
                                        'alias' => 'ListItem_virtual',
                                        'conditionType' => 'WITH',
                                        'condition' => 'ListItem_virtual.List = List'
                                            . ' AND entity.id = ListItem_virtual.entityId'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                'expected' => [
                    'select' => ['t4.name as c1'],
                    'from' => [
                        [
                            'table' => 'Acme\Entity\TestEntity',
                            'alias' => 't1',
                        ]
                    ],
                    'join' => [
                        'left' => [
                            [
                                'join' => 't1.ListItem_virtual',
                                'alias' => 't2',
                            ],
                            [
                                'join' => 'Oro\Bundle\TrackingBundle\Entity\List',
                                'alias' => 't3',
                                'conditionType' => 'WITH',
                                'condition' => 't3.entity = \'Acme\Entity\TestEntity\'',
                            ],
                            [
                                'join' => 'Oro\Bundle\TrackingBundle\Entity\ListItem',
                                'alias' => 't4',
                                'conditionType' => 'WITH',
                                'condition' => 't4.List = t3 AND t2.id = t4.entityId',
                            ]
                        ]
                    ]
                ]
            ],
        ];
    }
}
